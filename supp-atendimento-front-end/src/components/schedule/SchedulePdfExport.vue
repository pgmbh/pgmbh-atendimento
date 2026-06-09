<template>
  <v-tooltip :text="disabled ? 'Selecione um projeto para exportar o cronograma' : 'Exportar cronograma em PDF'" location="bottom">
    <template #activator="{ props }">
      <span v-bind="props">
        <v-btn
          color="primary"
          prepend-icon="mdi-file-pdf-box"
          :disabled="disabled"
          @click="exportPdf"
        >
          Exportar PDF
        </v-btn>
      </span>
    </template>
  </v-tooltip>
</template>

<script setup>
import { authService } from '@/services/auth.service'

const props = defineProps({
  disabled: { type: Boolean, default: false },
  items: { type: Array, default: () => [] },
  project: { type: Object, default: null },
})

function formatDate(d) {
  if (!d) return ''
  const [y, m, day] = d.split('-')
  return `${day}/${m}/${y}`
}

const STATUS_LABEL = {
  NOVO: 'Novo', NEW: 'Novo',
  ABERTO: 'Aberto', OPEN: 'Aberto',
  IN_PROGRESS: 'Em Andamento', 'EM ANDAMENTO': 'Em Andamento',
  RESOLVED: 'Resolvido',
  RETORNO: 'Retorno',
  CONCLUDED: 'Concluído', CONCLUIDO: 'Concluído',
  CANCELADO: 'Cancelado',
}
function translateStatus(s) { return STATUS_LABEL[s] ?? s }
function isInProgress(s) { return s === 'IN_PROGRESS' || s === 'EM ANDAMENTO' }

function exportPdf() {
  if (!props.project) return

  const user = authService.getAttendantData() || authService.getUser()
  const userName = user?.name || 'Usuário'
  const generatedAt = new Date().toLocaleDateString('pt-BR')
  const projectLabel = `[${props.project.acronym}] ${props.project.name}`

  const rows = props.items.map(item => {
    const conclusion = item.date_conclusion
      ? formatDate(item.date_conclusion)
      : isInProgress(item.status) ? 'Em andamento' : 'Não iniciado'

    return `
      <tr>
        <td>${item.id}</td>
        <td>${item.title || ''}</td>
        <td>${formatDate(item.date_start)}</td>
        <td>${conclusion}</td>
        <td>${translateStatus(item.status) || ''}</td>
        <td>${item.observation || ''}</td>
      </tr>`
  }).join('')

  const html = `<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Cronograma — ${projectLabel}</title>
<style>
  ${printCSS()}
</style>
</head>
<body>
  <div class="header no-print-header">
    <img src="/brasao-pbh.png" alt="Brasão PBH" class="brasao" onerror="this.style.display='none'" />
    <div class="header-text">
      <h2>PREFEITURA MUNICIPAL DE BELO HORIZONTE</h2>
      <h3>CRONOGRAMA DE ATIVIDADES</h3>
    </div>
  </div>
  <p class="project-title">${projectLabel}</p>
  <p class="meta">Gerado em: ${generatedAt} | Usuário: ${userName}</p>
  <table>
    <colgroup>
      <col class="col-id" />
      <col class="col-title" />
      <col class="col-start" />
      <col class="col-end" />
      <col class="col-status" />
      <col class="col-obs" />
    </colgroup>
    <thead>
      <tr>
        <th>ID</th>
        <th>Atividade</th>
        <th>Início</th>
        <th>Conclusão</th>
        <th>Status</th>
        <th>Descrição</th>
      </tr>
    </thead>
    <tbody>
      ${rows || '<tr><td colspan="6">Nenhuma demanda encontrada.</td></tr>'}
    </tbody>
  </table>
</body>
</html>`

  const win = window.open('', '_blank', 'width=1000,height=700')
  win.document.write(html)
  win.document.close()
  win.focus()
  setTimeout(() => {
    win.print()
    win.close()
  }, 500)
}

function printCSS() {
  return `
body { font-family: Arial, sans-serif; font-size: 10px; margin: 0; padding: 0; }
.header { display: flex; align-items: center; margin-bottom: 12px; }
.brasao { width: 60px; height: auto; margin-right: 12px; }
.header-text h2, .header-text h3 { margin: 0; }
.project-title { font-size: 14px; font-weight: bold; margin-bottom: 4px; }
.meta { font-size: 9px; color: #666; margin-bottom: 8px; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; }
th, td { border: 1px solid #ccc; padding: 4px 8px; text-align: left; vertical-align: top; word-break: break-word; white-space: pre-wrap; overflow-wrap: break-word; }
th { background-color: #1565C0; color: white; }
tr:nth-child(even) { background-color: #f5f5f5; }
col.col-id      { width: 4%; }
col.col-title   { width: 22%; }
col.col-start   { width: 9%; }
col.col-end     { width: 9%; }
col.col-status  { width: 9%; }
col.col-obs     { width: 47%; }
@media print {
  body { font-family: Arial, sans-serif; font-size: 10px; }
  .no-print { display: none !important; }
  .project-title { font-size: 14px; font-weight: bold; margin-bottom: 8px; }
  table { width: 100%; border-collapse: collapse; table-layout: fixed; }
  th, td { border: 1px solid #ccc; padding: 4px 8px; vertical-align: top; word-break: break-word; white-space: pre-wrap; overflow-wrap: break-word; }
  th { background-color: #1565C0; color: white; }
  tr:nth-child(even) { background-color: #f5f5f5; }
  @page { size: A4 landscape; margin: 1cm; }
}
`
}
</script>
