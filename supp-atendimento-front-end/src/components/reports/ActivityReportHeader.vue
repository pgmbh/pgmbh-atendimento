<template>
  <div class="report-header">
    <div class="header-logo">
      <img src="/brasao-pbh.png" alt="Brasão PBH" class="brasao" />
    </div>
    <div class="header-text">
      <div class="institution">PREFEITURA MUNICIPAL DE BELO HORIZONTE</div>
      <div class="department">Procuradoria Geral do Municipio</div>
      <div class="report-title">RELATÓRIO DE ATIVIDADES</div>
    </div>
  </div>
</template>

<style scoped>
.report-header {
  display: flex;
  align-items: center;
  gap: 20px;
  padding-bottom: 16px;
  border-bottom: 2px solid #1a237e;
  margin-bottom: 20px;
}
.brasao {
  width: 80px;
  height: auto;
}
.header-text {
  flex: 1;
  text-align: center;
}
.institution {
  font-size: 14px;
  font-weight: 700;
  color: #1a237e;
  text-transform: uppercase;
}
.department {
  font-size: 12px;
  color: #333;
  margin-top: 2px;
}
.report-title {
  font-size: 16px;
  font-weight: 700;
  color: #1a237e;
  margin-top: 8px;
  text-transform: uppercase;
  letter-spacing: 1px;
}
</style>
