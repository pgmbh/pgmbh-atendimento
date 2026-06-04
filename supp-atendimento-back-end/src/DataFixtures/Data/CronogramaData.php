<?php

// Gerado automaticamente a partir de Cronograma.xlsx — não editar manualmente.
return array (
  0 => 
  array (
    'row' => 5,
    'title' => 'Fundação documental do projeto (antes do Hermes)',
    'observacao' => 'Cinco documentos em services/docs/ formam a base sobre a qual o resto foi construído: arquitetura, escolha de protocolos, hardening em 2 fases e RFC multi-tenant.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-04-28',
    'date_end' => '2026-05-06',
  ),
  1 => 
  array (
    'row' => 6,
    'title' => 'Mapa do território — README.md + architecture.md',
    'observacao' => '4 contêineres (mcp-legislacao, mcp-jurisprudencia, mcp-ljpgmbh, mcp-mongo); 3 camadas funcionais (Borda, Domínio, RAG núcleo); regra dura "REST entre camadas, gRPC dentro do núcleo RAG".',
    'status_raw' => 'Concluído',
    'date_start' => '2026-04-28',
    'date_end' => '2026-04-28',
  ),
  2 => 
  array (
    'row' => 7,
    'title' => 'Comparativo formal de protocolos — communication-patterns.md',
    'observacao' => 'REST vs gRPC vs Message Bus com critérios: multiplexing, contratos tipados, back-pressure, observabilidade. Conclusão: padrão híbrido (REST na borda, gRPC no núcleo RAG, bus reservado pra futuro).',
    'status_raw' => 'Concluído',
    'date_start' => '2026-04-30',
    'date_end' => '2026-04-30',
  ),
  3 => 
  array (
    'row' => 8,
    'title' => 'Levantamento de hardening — hardening.md',
    'observacao' => 'Três objetivos: (1) único ponto de acesso via proxy reverso, (2) multi-usuário organizado com identidade/sessão, (3) segurança a todo custo (TLS, headers, fechamento de portas). Roadmap em P0 (rede) e P1 (identidade).',
    'status_raw' => 'Concluído',
    'date_start' => '2026-04-30',
    'date_end' => '2026-04-30',
  ),
  4 => 
  array (
    'row' => 9,
    'title' => 'Plano detalhado P0 — p0-hardening-plan.md',
    'observacao' => 'Caddy como único ponto de entrada (:80/:443) com TLS automático; fechamento de exposição direta de ljpgmbh:8000/legislacao:8000/jurisprudencia:8000/rag-gateway:8080; security headers (HSTS, X-Content-Type-Options, X-Frame-Options, Referrer-Policy, CSP); rollback por bloco atômico.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-04-30',
    'date_end' => '2026-04-30',
  ),
  5 => 
  array (
    'row' => 10,
    'title' => 'Plano detalhado P1 — p1-hardening-plan.md',
    'observacao' => 'Autenticação delegada ao SUPP via JWT RS256: SuppAuthClient (/auth/get_token, /auth/refresh_token), SuppJwtAuthenticator (firebase/jwt + chave pública RSA), AuthController emitindo cookie HttpOnly+Secure+SameSite=Strict, IsGranted(\'ROLE_USER\') nos controllers, redirect /login em requests HTML, 401 JSON em /api/*.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-04-30',
    'date_end' => '2026-04-30',
  ),
  6 => 
  array (
    'row' => 11,
    'title' => 'Catálogo de endpoints — endpoints.md',
    'observacao' => 'Inventário das 4 portas internas (legislacao, jurisprudencia, rag-gateway, ljpgmbh) com todos os /api/*, /v1/index/*, /v1/query/*, /v1/debug/*, /v1/infra/*/status. RBAC efetivo (ROLE_USER, ROLE_COORDENADOR_SETOR, ROLE_PROCURADOR_FEDERAL...) e códigos de erro padrão.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-04-30',
    'date_end' => '2026-04-30',
  ),
  7 => 
  array (
    'row' => 12,
    'title' => 'Trocar o armazém de documentos do sistema (do antigo para o que já é usado no SUPP)',
    'observacao' => 'A peça interna que guarda toda a base do sistema foi trocada pelo mesmo tipo usado pelo SUPP.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-07',
    'date_end' => '2026-05-07',
  ),
  8 => 
  array (
    'row' => 13,
    'title' => 'Migração MongoDB → PostgreSQL',
    'observacao' => '18 coleções migradas via script ETL idempotente (bin/etl-mongo-to-pg.php), 3,34M docs em ~4 min, ON CONFLICT DO UPDATE.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-07',
    'date_end' => '2026-05-07',
  ),
  9 => 
  array (
    'row' => 14,
    'title' => 'Camada JsonbStore/JsonbCollection mimetizando MongoDB\\Collection sobre (id text PK, data jsonb)',
    'observacao' => 'Suporta find / findOne / insertOne / updateOne com $set/$inc/$push, countDocuments, distinct, fullTextSearch via GIN com analyzer português + unaccent + trigrams.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-07',
    'date_end' => '2026-05-07',
  ),
  10 => 
  array (
    'row' => 15,
    'title' => 'Migration SQL 001_initial_schema.sql',
    'observacao' => '19 tabelas, 55 índices incluindo FTS GIN com pesos A/B/C/D em numero/titulo/ementa/urn. Schema \'legislativo\'.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-07',
    'date_end' => '2026-05-07',
  ),
  11 => 
  array (
    'row' => 16,
    'title' => 'MongoDbService refatorado sem reescrever (1500 LOC mantidas)',
    'observacao' => 'Construtor passou a receber JsonbStore; nome da classe mantido por compatibilidade com DI e scripts CLI.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-07',
    'date_end' => '2026-05-07',
  ),
  12 => 
  array (
    'row' => 17,
    'title' => 'Mongo continua de pé como recovery; nenhum caminho HTTP toca mais nele',
    'observacao' => 'composer.json mantém ext-mongodb temporariamente por causa do bin/etl. Cleanup futuro.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-07',
    'date_end' => '2026-05-07',
  ),
  13 => 
  array (
    'row' => 18,
    'title' => 'Acabar com o engasgo do buscador inteligente quando duas perguntas competiam por temas diferentes',
    'observacao' => 'Implementação da Opção 3 do RFC multi-tenant no gateway RAG.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-08',
    'date_end' => '2026-05-08',
  ),
  14 => 
  array (
    'row' => 19,
    'title' => 'Per-request collection no gateway: + string collection nos protos vectorstore.proto e retriever.proto (Search/Store/GetByIds/GetByParentIds/Delete/Hybrid Request)',
    'observacao' => 'Mudança propaga em 3 serviços: vectorstore-svc, retriever-svc, gateway-api.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-08',
    'date_end' => '2026-05-08',
  ),
  15 => 
  array (
    'row' => 20,
    'title' => 'Pool LRU OrderedDict[str, _CollHandle] com asyncio.Lock (cap=8) em qdrant_backend.py',
    'observacao' => 'Coleção inédita é lazy-introspectada via qdrant.get_collection; LRU evict ao bater cap.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-08',
    'date_end' => '2026-05-08',
  ),
  16 => 
  array (
    'row' => 21,
    'title' => 'DebugSearchRequest no gateway-api ganhou campo `collection: str',
    'observacao' => 'Concluído',
    'status_raw' => '46150.0',
    'date_start' => NULL,
    'date_end' => '2026-05-08',
  ),
  17 => 
  array (
    'row' => 22,
    'title' => 'Timeout do AsyncQdrantClient elevado de 5s para 30s no cold-start',
    'observacao' => 'Default não cobria primeiro carregamento do handle no pool.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-08',
    'date_end' => '2026-05-08',
  ),
  18 => 
  array (
    'row' => 23,
    'title' => 'Padronizar para que a IA rápida seja a padrão e a IA completa só quando o usuário pedir',
    'observacao' => 'Default `rag-direct` em todo o stack; `hermes-agent` (tool loop + thinking) só sob pedido explícito.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-11',
    'date_end' => '2026-05-11',
  ),
  19 => 
  array (
    'row' => 24,
    'title' => 'editor.html.twig: ragState.cfg.perfil default \'resposta-rapida\'; <option> ordenado com resposta-rapida primeiro',
    'observacao' => 'Migração de localStorage: aceita config antiga mas força \'resposta-rapida\' como fallback de perfil inválido.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-11',
    'date_end' => '2026-05-11',
  ),
  20 => 
  array (
    'row' => 25,
    'title' => 'RagQueryController::$mode = $body[\'mode\'] ?? \'rag-direct\' (default e fallback)',
    'observacao' => 'Antes era \'hermes-agent\'.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-11',
    'date_end' => '2026-05-11',
  ),
  21 => 
  array (
    'row' => 26,
    'title' => 'supervisor/main.py: SessionState.mode = \'rag-direct\'; Supervisor.start(mode=\'rag-direct\'); mode_norm fallback',
    'observacao' => 'Cobertura nas 3 camadas (dataclass default + handler default + normalização de input inválido).',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-11',
    'date_end' => '2026-05-11',
  ),
  22 => 
  array (
    'row' => 27,
    'title' => '/api/rag/resumir e JS `ragResumir` respeitam o perfil escolhido (mode/model/top_k)',
    'observacao' => 'Antes hardcodava `mode=\'hermes-agent\'` e `top_k=30`; agora repassa o que vier do cliente.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-11',
    'date_end' => '2026-05-11',
  ),
  23 => 
  array (
    'row' => 28,
    'title' => 'Resolver o travamento total da IA no computador sem placa de vídeo',
    'observacao' => 'Em CPU pura (Ryzen 5650G) o ollama-runner entrava em loop infinito gastando 1000%+ CPU sem cliente requisitando.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-11',
    'date_end' => '2026-05-11',
  ),
  24 => 
  array (
    'row' => 29,
    'title' => 'Removida OLLAMA_FLASH_ATTENTION=true do docker-compose.yml',
    'observacao' => 'Flash attention exige otimizações de GPU; em CPU causa loop degenerado.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-11',
    'date_end' => '2026-05-11',
  ),
  25 => 
  array (
    'row' => 30,
    'title' => 'Removida OLLAMA_KV_CACHE_TYPE=q8_0',
    'observacao' => 'KV cache quantizado q8_0 só é estável com flash attention ativo (que só funciona em GPU).',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-11',
    'date_end' => '2026-05-11',
  ),
  26 => 
  array (
    'row' => 31,
    'title' => 'OLLAMA_CONTEXT_LENGTH reduzido de 65536 para 16384',
    'observacao' => '16k cobre rag-direct (5-20 chunks + system + msg). Pode subir para 24k/32k se hermes-agent truncar.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-11',
    'date_end' => '2026-05-11',
  ),
  27 => 
  array (
    'row' => 32,
    'title' => 'Sintomas catalogados na memória de feedback do assistente',
    'observacao' => 'agent_runner sleeping após emit `reranking`; POST /v1/chat/completions nunca chega ao Ollama ou só aparece com HTTP 500 após 8-22min; threads em 99%+ por thread × 5-12 threads.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-11',
    'date_end' => '2026-05-11',
  ),
  28 => 
  array (
    'row' => 33,
    'title' => 'Preparar o texto dos PDFs para a IA conseguir realmente entender',
    'observacao' => 'Verbalizador determinístico + chunking boundary-aware + overlap adaptativo no ProcessoIndexerService.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-12',
    'date_end' => '2026-05-12',
  ),
  29 => 
  array (
    'row' => 34,
    'title' => 'App\\Service\\TextoVerbalizador — detector de tabelas espacializadas',
    'observacao' => 'Heurística: cabeçalho ≥3 tokens maiúsculos sem dígitos + ≥2 linhas alinhadas (±1 token), com pelo menos 1 token alfanumérico misto. Converte em "Tabela X / Y / Z: - x A, y B, z C.".',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-12',
    'date_end' => '2026-05-12',
  ),
  30 => 
  array (
    'row' => 35,
    'title' => 'Expansor de ~40 siglas do domínio fiscal/jurídico',
    'observacao' => 'Tributários (IPTU, ICMS, ITCMD, COFINS, PIS, INSS, FGTS, IPI, ITBI, ISS, IRPF, IRPJ, CSLL, IPVA, TCDL); documentos (CDA, CND, DARF, DAS, DCTF, GNRE, NF, NFe, NFSe); identificadores (NUP, CNJ, CNPJ, CPF, NIRE); órgãos (SMFA, SEFAZ, PGFN, PGE, PGM, RFB); atos normativos (LC, EC, MP, CF, CTN, CDC, CLT, CPC, CPP). Expansão apenas na 1ª ocorrência por texto.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-12',
    'date_end' => '2026-05-12',
  ),
  31 => 
  array (
    'row' => 36,
    'title' => 'Prefixos para datas e valores nus em linhas isoladas',
    'observacao' => 'Regex `^\\d{2}/\\d{2}/\\d{4}$` → "Data: <X>"; `^R\\$\\s*[\\d.]+(,\\d{2})?$` → "Valor: <X>".',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-12',
    'date_end' => '2026-05-12',
  ),
  32 => 
  array (
    'row' => 37,
    'title' => 'Chunking boundary-aware (findBoundary) substituindo corte cego em $window',
    'observacao' => 'Busca último separador no range [start+0.7W, start+W] na ordem: \\n\\n, \\n, ". ", "; ", ": ", ", ", " ". Fallback para corte cego em $window se nada casar.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-12',
    'date_end' => '2026-05-12',
  ),
  33 => 
  array (
    'row' => 38,
    'title' => 'Overlap adaptativo: docs ≤ 4×window usam overlap=0',
    'observacao' => 'Em CDAs (~3.5KB) o overlap de 150 chars amplifica ruído do template repetitivo (cabeçalho ~375 chars idêntico entre peças do mesmo processo).',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-12',
    'date_end' => '2026-05-12',
  ),
  34 => 
  array (
    'row' => 39,
    'title' => 'Conectar o sistema ao SUPP de homologação com autenticação adequada',
    'observacao' => 'Migração do acesso direto ao OpenSearch :9200 para REST autenticada com JWT forward.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-13',
    'date_end' => '2026-05-13',
  ),
  35 => 
  array (
    'row' => 40,
    'title' => 'SUPP_BASE_URL default no docker-compose.yml = https://suppbackend-hml.pgmbh.org',
    'observacao' => 'Override local via export SUPP_BASE_URL=http://host.docker.internal:8000.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-13',
    'date_end' => '2026-05-13',
  ),
  36 => 
  array (
    'row' => 41,
    'title' => 'App\\Service\\SuppComponenteDigitalApiClient::buscarTextoExtraido($jwt, $cdId)',
    'observacao' => 'Encapsula GET /v1/administrativo/componente_digital/{id}/texto-extraido com JWT forward.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-13',
    'date_end' => '2026-05-13',
  ),
  37 => 
  array (
    'row' => 42,
    'title' => 'App\\Service\\SuppComponenteDigitalSearchClient::pecasDoProcesso/contagemEmLote/contarPecasDoProcesso',
    'observacao' => 'GET /v1/administrativo/componente_digital/search com `where={"documento.juntadaAtual.volume.processo.id":"eq:<id>"}` (camelCase obrigatório).',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-13',
    'date_end' => '2026-05-13',
  ),
  38 => 
  array (
    'row' => 43,
    'title' => 'contagemEmLote em paralelo via Symfony HttpClient + SplObjectStorage',
    'observacao' => '$http->stream($reqs) yieldz responses com chaves diferentes; array_search por identidade falha. SplObjectStorage resolve. Cap 200 ids/chamada.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-13',
    'date_end' => '2026-05-13',
  ),
  39 => 
  array (
    'row' => 44,
    'title' => 'JWT forward em todos os controllers que tocam SUPP',
    'observacao' => 'SuppProcessoSearchClient::extractJwtFromRequest($request) lê cookie `jwt` ou header Authorization Bearer; repassa nos chamadores.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-13',
    'date_end' => '2026-05-13',
  ),
  40 => 
  array (
    'row' => 45,
    'title' => 'SuppEsClient marcado @deprecated; sobrevive como fallback CLI sem JWT',
    'observacao' => 'ProcessoIndexerService usa SuppApi quando jwtToken != null; cai no ES :9200 só em scripts CLI sem JWT do usuário.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-13',
    'date_end' => '2026-05-13',
  ),
  41 => 
  array (
    'row' => 46,
    'title' => 'Timeouts: SuppProcessoSearchClient::request 20s→180s; SuppComponenteDigitalApiClient 15s→60s; SuppComponenteDigitalSearchClient 30s',
    'observacao' => 'SUPP hml com populate=populateAll oscila 1-60s+ por GC / queries Doctrine pesadas.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-13',
    'date_end' => '2026-05-13',
  ),
  42 => 
  array (
    'row' => 47,
    'title' => 'Resolver problemas de login do procurador no SUPP de homologação',
    'observacao' => 'Duas causas combinadas: cookie secure barrado por TLS auto-assinado + chave RSA pública incompatível com hml.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-13',
    'date_end' => '2026-05-13',
  ),
  43 => 
  array (
    'row' => 48,
    'title' => 'AuthController::login — Cookie::create(\'jwt\')->withSecure($request->isSecure())',
    'observacao' => 'Chrome descarta cookies `secure` em HTTPS com cert auto-assinado mesmo após o usuário aceitar o aviso; era ->withSecure(true).',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-13',
    'date_end' => '2026-05-13',
  ),
  44 => 
  array (
    'row' => 49,
    'title' => 'Substituição de config/jwt/supp-public.pem pela chave RSA do hml',
    'observacao' => 'Backup da chave dev em supp-public.pem.dev. RS256 RFC 3447. Antes: `Signature verification failed` em todos os endpoints autenticados.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-13',
    'date_end' => '2026-05-13',
  ),
  45 => 
  array (
    'row' => 50,
    'title' => 'Consertar o serviço do SUPP que entrega o texto extraído de peças',
    'observacao' => 'Bug do alias multi-index no ElasticsearchClient::get(); patch enviado em branch separada do supp-administrativo-backend.',
    'status_raw' => 'Aguardando merge no SUPP',
    'date_start' => '2026-05-13',
    'date_end' => '2026-05-14',
  ),
  46 => 
  array (
    'row' => 51,
    'title' => 'Diagnóstico do erro illegal_argument_exception em GET /componente_digital/{id}/texto-extraido',
    'observacao' => '`alias [componente_digital] has more than one index associated with it [componente_digital-202503, -202504, ...]`. Em hml o alias é write alias de ILM com partitioning mensal.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-13',
    'date_end' => '2026-05-13',
  ),
  47 => 
  array (
    'row' => 52,
    'title' => 'Patch: client->get([\'index\'=>alias, \'id\'=>$id]) → client->search([\'body\'=>[\'size\'=>1, \'query\'=>[\'term\'=>[\'_id\'=>$id]], \'_source\'=>[\'excludes\'=>[\'conteudo\']]]])',
    'observacao' => 'search com term em _id aceita alias multi-índice; performance equivalente pelo Lucene (inverted index em _id).',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-13',
    'date_end' => '2026-05-13',
  ),
  48 => 
  array (
    'row' => 53,
    'title' => 'Branch fix-ep-ljpgmbh criada + commit 77ec71ea9 + push para origin',
    'observacao' => 'Contrato público da rota inalterado: mesma response JSON; isGranted(\'VIEW\') em $documento/$processo/$classificacao preservado.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-13',
    'date_end' => '2026-05-13',
  ),
  49 => 
  array (
    'row' => 54,
    'title' => 'Diagnosticar por que muitos processos novos aparecem com zero peças',
    'observacao' => 'Pipeline de indexação OpenSearch do SUPP hml parou em abr-mai/2026.',
    'status_raw' => 'Reportar ao SUPP',
    'date_start' => '2026-05-14',
    'date_end' => '2026-05-14',
  ),
  50 => 
  array (
    'row' => 55,
    'title' => 'Comparação ES vs Doctrine via /v1/administrativo/componente_digital/search e /v1/administrativo/documento',
    'observacao' => 'Doctrine retorna total via Postgres; ES retorna total via índice. Path no Doctrine é juntadaAtual.volume.processo.id (sem prefixo documento.) e quebra com SQLSTATE 22007 ao filtrar por criadoEm.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-14',
    'date_end' => '2026-05-14',
  ),
  51 => 
  array (
    'row' => 56,
    'title' => 'Cobertura por mês (criadoEm dos componentes 2026): jan 5723; fev 5; mar 88; abr 853; mai 10',
    'observacao' => 'Indexação OpenSearch travou em abr-mai/2026; jan/2026 OK (5,7k); mai com 10 componentes para 4,7k processos.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-14',
    'date_end' => '2026-05-14',
  ),
  52 => 
  array (
    'row' => 57,
    'title' => 'Geração de CSV processos x peças para janela 2026-01-01..07 (30 procs todos OK)',
    'observacao' => 'Janela com ES==Doctrine em todos; confirma que índice estava saudável no início do ano. Vários casos travados em total=200 (limite default do endpoint /documento).',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-14',
    'date_end' => '2026-05-14',
  ),
  53 => 
  array (
    'row' => 58,
    'title' => 'Decisão arquitetural: NÃO adicionar fallback Doctrine em SuppComponenteDigitalSearchClient::contagemEmLote',
    'observacao' => 'Razão: texto extraído só vive no ES. Mostrar peças que não podem ser indexadas confunde o procurador. Contagem reflete o que está indexado de fato.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-14',
    'date_end' => '2026-05-14',
  ),
  54 => 
  array (
    'row' => 59,
    'title' => 'Acabar com as páginas em branco na lista de processos',
    'observacao' => 'Modo dense client-side substitui paginação server-side quando filtro de peças está ativo.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-14',
    'date_end' => '2026-05-14',
  ),
  55 => 
  array (
    'row' => 60,
    'title' => 'proc-filter-bar com display:flex no markup (em vez de display:none + toggle JS)',
    'observacao' => 'Antes a barra só aparecia após o 1º renderProcResults; agora persistente e permite ativar filtro antes da busca.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-14',
    'date_end' => '2026-05-14',
  ),
  56 => 
  array (
    'row' => 61,
    'title' => 'runProcSearchDense(): limit=500, offset=0 + contagem-bulk em batches de 200 IDs + filter client-side + procPg.dense',
    'observacao' => 'Paginação local sobre lista densa via Array.prototype.slice; sem páginas em branco, sem buracos.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-14',
    'date_end' => '2026-05-14',
  ),
  57 => 
  array (
    'row' => 62,
    'title' => 'Cap de IDs por chamada de contagem-bulk subido de 50 para 200',
    'observacao' => 'Era 50 (insuficiente para pageSize=100 ou modo dense em batches); SUPP suporta o paralelismo com folga.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-14',
    'date_end' => '2026-05-14',
  ),
  58 => 
  array (
    'row' => 63,
    'title' => 'Listeners: input com debounce 300ms para min-pecas; change direto no checkbox com-pecas',
    'observacao' => 'Evita fetch storm durante digitação no campo numérico.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-14',
    'date_end' => '2026-05-14',
  ),
  59 => 
  array (
    'row' => 64,
    'title' => 'Documentar tudo no relatório principal e gerar este cronograma',
    'observacao' => 'Capítulo novo no LaTeX + cronograma em paisagem + planilha não-técnica.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-14',
    'date_end' => '2026-05-14',
  ),
  60 => 
  array (
    'row' => 65,
    'title' => 'docs/hermes-integration/relatorio-final.tex reescrito em estilo narrativo (\\section A jornada da integração com o SUPP em hml)',
    'observacao' => 'Imita o estilo da seção anterior do mesmo documento (A jornada do SSE longo). Capítulo antigo preservado em \\iffalse...\\fi.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-14',
    'date_end' => '2026-05-14',
  ),
  61 => 
  array (
    'row' => 66,
    'title' => 'xltabular em \\begin{landscape}...\\end{landscape} no final do PDF',
    'observacao' => 'pdflscape habilitado no preamble; 14 linhas com Data/Frente/Atividade/Resultado/Status.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-14',
    'date_end' => '2026-05-14',
  ),
  62 => 
  array (
    'row' => 67,
    'title' => 'Cronograma em Google Sheets via MCP do Google Drive (este arquivo)',
    'observacao' => 'Linguagem leiga nos itens-pai e técnica nos subitens; pronto pra agrupar via Data → Group rows no Sheets.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-14',
    'date_end' => '2026-05-14',
  ),
  63 => 
  array (
    'row' => 68,
    'title' => 'Cachear sessão SUPP e corrigir o \'texto integral\'',
    'observacao' => 'Reuso de cliente autenticado entre requests + fix do fallback que silenciava erro antes de devolver ao front.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-15',
    'date_end' => '2026-05-15',
  ),
  64 => 
  array (
    'row' => 69,
    'title' => 'Sessão SUPP cacheada (reuso de cliente autenticado entre requests)',
    'observacao' => 'Sem reabrir conexão a cada chamada; reduz round-trips de auth do SUPP em rajadas de requests.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-15',
    'date_end' => '2026-05-15',
  ),
  65 => 
  array (
    'row' => 70,
    'title' => 'Fix do fallback do \'texto integral\' que silenciava erro antes de devolver ao front',
    'observacao' => 'Antes: erro do SUPP era engolido e front recebia body vazio. Agora: erro é propagado com mensagem visível.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-15',
    'date_end' => '2026-05-15',
  ),
  66 => 
  array (
    'row' => 71,
    'title' => 'Simplificar o sistema: remover o agente jurídico do caminho principal',
    'observacao' => 'Branch workingsystem: caminho único rag-direct com qwen2.5:7b em 16k.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-16',
    'date_end' => '2026-05-19',
  ),
  67 => 
  array (
    'row' => 72,
    'title' => 'Branch workingsystem criada — remoção do hermes-agent do hot path',
    'observacao' => 'Agente jurídico (Hermes) só seria ativado sob pedido explícito; padrão passou a ser rag-direct.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-16',
    'date_end' => '2026-05-16',
  ),
  68 => 
  array (
    'row' => 73,
    'title' => 'Vendor AIAgent + skills + tool loop desligados do supervisor',
    'observacao' => 'Diminuição da superfície de bugs no caminho síncrono; SSE mais simples.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-17',
    'date_end' => '2026-05-18',
  ),
  69 => 
  array (
    'row' => 74,
    'title' => 'Padronização: qwen2.5:7b com OLLAMA_CONTEXT_LENGTH=16384 fixo',
    'observacao' => 'Antes 32k+ \'por garantia\'; 16k cobre 5-20 chunks rerankeados + system + msg com folga em CPU.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-19',
    'date_end' => '2026-05-19',
  ),
  70 => 
  array (
    'row' => 75,
    'title' => 'Trazer dados do processo do SUPP pro editor de texto',
    'observacao' => 'Processo Tratado\' como árvore drag-source + contexto injetado em /resumir + fix Qdrant.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-20',
    'date_end' => '2026-05-20',
  ),
  71 => 
  array (
    'row' => 76,
    'title' => 'populate=populateAll + interessados.* + assuntos.* explícitos no /api/processo/{id}',
    'observacao' => 'findById passou a aceitar entity JSON-LD bruto; partes do processo aparecem na árvore do editor.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-20',
    'date_end' => '2026-05-20',
  ),
  72 => 
  array (
    'row' => 77,
    'title' => 'Árvore \'Processo Tratado\' como drag-source de metadados SUPP',
    'observacao' => 'Cada nó (autor, réu, valor, objeto) é arrastável pro editor de texto.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-20',
    'date_end' => '2026-05-20',
  ),
  73 => 
  array (
    'row' => 78,
    'title' => 'ProcFieldBlot customizado do Quill recebe o drop direto no texto',
    'observacao' => 'Blot novo do Quill que materializa o nó arrastado como token tipado no texto.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-20',
    'date_end' => '2026-05-20',
  ),
  74 => 
  array (
    'row' => 79,
    'title' => 'Contexto do processo (autor/réu/objeto/valor) injetado pelo front no prompt /resumir',
    'observacao' => 'Sem refetch SUPP no backend; síntese ancorada nos dados que o procurador vê na tela.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-20',
    'date_end' => '2026-05-20',
  ),
  75 => 
  array (
    'row' => 80,
    'title' => 'ulimits.nofile=65536 no compose do rag-qdrant',
    'observacao' => 'Fim do \'Too many open files\' em coleções grandes (132k pontos em rag-chunks-v3).',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-20',
    'date_end' => '2026-05-20',
  ),
  76 => 
  array (
    'row' => 81,
    'title' => 'Layout 3-col definitivo e limpeza de HTML antes de indexar',
    'observacao' => 'UI estabilizada + chunks mais limpos no embedder.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-21',
    'date_end' => '2026-05-21',
  ),
  77 => 
  array (
    'row' => 82,
    'title' => 'Layout 3-col estável; busca textual no header; fix do gotcha do flex',
    'observacao' => 'Overflow em coluna interna do flex resolvido; barra de busca persistente.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-21',
    'date_end' => '2026-05-21',
  ),
  78 => 
  array (
    'row' => 83,
    'title' => 'HtmlSanitizer antes de chunkar peças: <br>→\\n, <li> com prefixo; tabela→prosa',
    'observacao' => 'Sem ruído de tags HTML no embedder; expansão de siglas e prefixos de data/valor já estavam em vigor desde 12/05.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-21',
    'date_end' => '2026-05-21',
  ),
  79 => 
  array (
    'row' => 84,
    'title' => 'Promover o reranker melhor e ajustar o pool',
    'observacao' => 'Qualidade do rerank melhora; latência cabe no orçamento CPU.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-22',
    'date_end' => '2026-05-22',
  ),
  80 => 
  array (
    'row' => 85,
    'title' => 'bge-reranker-v2-m3 re-promovido como default',
    'observacao' => 'Re-promoção após rollback breve no dia anterior por questão de latência/qualidade.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-22',
    'date_end' => '2026-05-22',
  ),
  81 => 
  array (
    'row' => 86,
    'title' => 'Pool de rerank 10x→6x top_k',
    'observacao' => 'De 10× para 6× pra acomodar CPU sem perder muito recall.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-22',
    'date_end' => '2026-05-22',
  ),
  82 => 
  array (
    'row' => 87,
    'title' => 'Quebrar artigos longos em pedaços de tamanho controlado',
    'observacao' => 'Branch tst-chk-size → evol-pt1: artigos >512 tokens viram N sub-chunks de tokens iguais via gateway.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-25',
    'date_end' => '2026-05-26',
  ),
  83 => 
  array (
    'row' => 88,
    'title' => 'POST /v1/debug/tokenize no rag-gateway (tokenizer xlm-roberta do bge-m3)',
    'observacao' => 'Lazy singleton via tokenizers + huggingface_hub; aceita max_tokens e devolve sub-textos preservando char offsets.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-25',
    'date_end' => '2026-05-25',
  ),
  84 => 
  array (
    'row' => 89,
    'title' => 'DocumentReadyBuilder fatia artigos >512 tokens server-side via gateway',
    'observacao' => 'Em caso de erro do gateway, fallback = chunk único (indexação nunca falha por causa do tokenizer).',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-25',
    'date_end' => '2026-05-26',
  ),
  85 => 
  array (
    'row' => 90,
    'title' => 'Sub-chunks ganham fragment id <art>_pN e replicam o prefixo contextual',
    'observacao' => 'Contextual retrieval (Anthropic): cada sub-chunk leva o cabeçalho da norma + posição estrutural.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-25',
    'date_end' => '2026-05-25',
  ),
  86 => 
  array (
    'row' => 91,
    'title' => 'Fix do construtor DocumentReadyBuilder (2→4 args) + services.yaml + scripts CLI',
    'observacao' => 'bin/indexar-rag.php e bin/indexar-rag-job.php instanciam manualmente (fora do DI); morreram com ArgumentCountError até esse fix.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-26',
    'date_end' => '2026-05-26',
  ),
  87 => 
  array (
    'row' => 92,
    'title' => 'Consertar a marcação de normas que estava quebrando o job de indexação',
    'observacao' => 'Operador $addToSet faltava no JsonbCollection desde a migração Mongo→Postgres.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-26',
    'date_end' => '2026-05-26',
  ),
  88 => 
  array (
    'row' => 93,
    'title' => 'Implementação do operador $addToSet no JsonbCollection',
    'observacao' => 'Mesmo dispatcher do $push, mas com check anti-duplicata; suporta $each para múltiplos valores.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-26',
    'date_end' => '2026-05-26',
  ),
  89 => 
  array (
    'row' => 94,
    'title' => 'Job de indexação marca rag_indexed_collections sem fatal',
    'observacao' => 'Lote anterior tinha sido escrito no Qdrant mas falhava na marcação Postgres — agora marca limpo.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-26',
    'date_end' => '2026-05-26',
  ),
  90 => 
  array (
    'row' => 95,
    'title' => 'Adicionar capacidade de visão (analisar imagens) ao sistema',
    'observacao' => 'Página /vision: upload de imagem → descrição via LLM multimodal local.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-26',
    'date_end' => '2026-05-26',
  ),
  91 => 
  array (
    'row' => 96,
    'title' => 'Página /vision com upload drag-drop + preview',
    'observacao' => 'Twig template com file input, preview, prompt textarea e área de resposta streaming.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-26',
    'date_end' => '2026-05-26',
  ),
  92 => 
  array (
    'row' => 97,
    'title' => 'POST /api/vision/describe com SSE token-stream',
    'observacao' => 'VisionController repassa chunks NDJSON do Ollama como SSE events (token/done/error); heartbeat per-chunk 5s.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-26',
    'date_end' => '2026-05-26',
  ),
  93 => 
  array (
    'row' => 98,
    'title' => 'Modelo llava-llama3 (Llama-3-8B int4 + mmproj f16 do xtuner) importado via Modelfile oficial',
    'observacao' => 'GGUFs em services/rag/models/llm/; ollama create llava-llama3 no rag-hermes-ollama. Cold start ~3s com cache quente.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-26',
    'date_end' => '2026-05-26',
  ),
  94 => 
  array (
    'row' => 99,
    'title' => 'OLLAMA_MAX_LOADED_MODELS=1 garante 1 modelo carregado por vez',
    'observacao' => 'Próprio Ollama é o guard de exclusão mútua (não o llm-guard); válido para qualquer cliente direto ou via proxy.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-26',
    'date_end' => '2026-05-26',
  ),
  95 => 
  array (
    'row' => 100,
    'title' => 'upload_max_filesize 2M→10M e post_max_size 12M no PHP',
    'observacao' => 'zz-uploads.ini copiado pra /usr/local/etc/php/conf.d/ no Dockerfile do ljpgmbh.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-26',
    'date_end' => '2026-05-26',
  ),
  96 => 
  array (
    'row' => 101,
    'title' => 'Consolidar mudanças na branch evol-pt1',
    'observacao' => 'Commit 1c2ed78 push para origin/evol-pt1; pronto pra PR contra master.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-26',
    'date_end' => '2026-05-26',
  ),
  97 => 
  array (
    'row' => 102,
    'title' => 'Commit 1c2ed78 — 13 arquivos, +538/-30 linhas',
    'observacao' => 'feat: chunk-by-token na indexação RAG + página /vision (llava-llama3).',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-26',
    'date_end' => '2026-05-26',
  ),
  98 => 
  array (
    'row' => 103,
    'title' => '*.gguf no .gitignore (modelos ficam em services/rag/models/llm/, fora do git)',
    'observacao' => 'GGUFs somam ~5.5GB; bind-mount preserva entre rebuilds via ./rag/models/llm:/models/llm:ro.',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-26',
    'date_end' => '2026-05-26',
  ),
  99 => 
  array (
    'row' => 104,
    'title' => 'Push para origin/evol-pt1 com upstream tracking',
    'observacao' => 'PR URL: https://github.com/supp-core/LJPGMPBH/pull/new/evol-pt1',
    'status_raw' => 'Concluído',
    'date_start' => '2026-05-26',
    'date_end' => '2026-05-26',
  ),
);
