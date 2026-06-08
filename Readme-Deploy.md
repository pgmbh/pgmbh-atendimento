# Deploy do `supp-atendimento` no AKS PROD — Passo a passo

Guia operacional para subir o projeto no cluster `aks-supp-prod`, namespace `supp-atendimento`, com ingress em `https://helpdesk.pgmbh.org`.

---

## Fase 0 — Pré-requisitos

### Ferramentas no host

```bash
kubectl version --client    # >= 1.25
az --version                # Azure CLI (login no ACR / verificar imagens)
docker --version            # só se for buildar/pushar imagens
openssl version             # para gerar chaves JWT
dig -v                      # para validar DNS
```

### Acessos necessários

- Login no Azure (`az login`) com permissão no resource group do AKS PROD
- Credenciais kubectl do `aks-supp-prod` configuradas no kubeconfig
- Push no ACR `suppregistry.azurecr.io` (só se for buildar)

### Confirmar contexto kubectl

```bash
kubectl config use-context aks-supp-prod
kubectl config current-context        # esperado: aks-supp-prod
kubectl get nodes                     # validação de conectividade
```

---

## Fase 1 — Verificações antes de tocar no cluster

### 1.1 Confirmar que as imagens existem no ACR

```bash
az acr login --name suppregistry

az acr repository show-tags \
  --name suppregistry \
  --repository supp-atendimento/backend \
  --output table | grep -E "6\.0\.0|TAG"

az acr repository show-tags \
  --name suppregistry \
  --repository supp-atendimento/webserver \
  --output table | grep -E "3\.0\.0|TAG"
```

Esperado: tags `6.0.0` (backend) e `3.0.0` (webserver). Se não existirem, builde/pushe antes — comandos em `Readme.md` (linhas 3–8).

### 1.2 Confirmar DNS do host de produção

```bash
dig +short helpdesk.pgmbh.org
# Esperado: 20.201.103.122 (IP do Traefik no aks-supp-prod)
```

Se não retornar ou retornar IP diferente: peça à equipe de rede para criar/atualizar o A record. Sem isso, o ingress existe mas o host externo não responde (só dá para testar via `curl -H "Host:"`).

### 1.3 Validar arquivos locais

```bash
cd /supp-core/supp-atendimento

ls kubernetes/                          # 8 yamls
ls certificado-supp/                    # supp-pgmbh-certificado.crt + supp-pgmbh-chave.key
openssl x509 -in certificado-supp/supp-pgmbh-certificado.crt -noout -dates -subject
# Confira: notAfter no futuro e subject compatível com helpdesk.pgmbh.org
```

---

## Fase 2 — Gerar chaves JWT

A passphrase precisa ser **exatamente** a mesma do `kubernetes/env-secret.yaml` (`JWT_PASSPHRASE`), senão o Symfony falha ao carregar a chave privada.

```bash
PASSPHRASE="aa75c85dfc63d2584dd56a47881d521f1399480d8143fe75e1ba8f915e2348b0"

mkdir -p /tmp/jwt-supp-atendimento
cd /tmp/jwt-supp-atendimento

openssl genpkey -out private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096 -pass pass:"$PASSPHRASE"
openssl pkey -in private.pem -out public.pem -pubout -passin pass:"$PASSPHRASE"

ls -la /tmp/jwt-supp-atendimento/
# Esperado:
#   private.pem (~3.4K)
#   public.pem  (~800 bytes)
```

---

## Fase 3 — Criar namespace

```bash
kubectl create namespace supp-atendimento
kubectl get ns supp-atendimento
```

---

## Fase 4 — Criar os 3 secrets

### 4.1 Secret de env (`.env.prod`)

```bash
cd /supp-core/supp-atendimento
kubectl apply -f kubernetes/env-secret.yaml
kubectl -n supp-atendimento get secret supp-atendimento-env
```

### 4.2 Secret das chaves JWT

```bash
kubectl create secret generic supp-atendimento-jwt \
  --from-file=private.pem=/tmp/jwt-supp-atendimento/private.pem \
  --from-file=public.pem=/tmp/jwt-supp-atendimento/public.pem \
  -n supp-atendimento

kubectl -n supp-atendimento get secret supp-atendimento-jwt
```

### 4.3 Secret TLS para o ingress

```bash
kubectl create secret tls supp-pgmbh-tls-secret \
  --cert=certificado-supp/supp-pgmbh-certificado.crt \
  --key=certificado-supp/supp-pgmbh-chave.key \
  -n supp-atendimento

kubectl -n supp-atendimento get secret supp-pgmbh-tls-secret
```

### 4.4 Conferência final

```bash
kubectl -n supp-atendimento get secrets
# Esperado 3 secrets:
#  supp-atendimento-env    Opaque              1
#  supp-atendimento-jwt    Opaque              2
#  supp-pgmbh-tls-secret   kubernetes.io/tls   2
```

---

## Fase 5 — Backend (PHP-FPM)

```bash
kubectl apply -f kubernetes/backend-deployment.yaml
kubectl apply -f kubernetes/backend-service.yaml

kubectl -n supp-atendimento rollout status deploy/backend --timeout=180s
kubectl -n supp-atendimento get pods -l app=backend
```

Se ficar em `ImagePullBackOff`:

```bash
kubectl -n supp-atendimento describe pod -l app=backend | tail -30
```

Se ficar em `CrashLoopBackOff`:

```bash
kubectl -n supp-atendimento logs deploy/backend --tail=100
```

---

## Fase 6 — Webserver (Nginx)

```bash
kubectl apply -f kubernetes/webserver-deployment.yaml
kubectl apply -f kubernetes/webserver-service.yaml

kubectl -n supp-atendimento rollout status deploy/webserver --timeout=120s
kubectl -n supp-atendimento get pods -l app=webserver
```

---

## Fase 7 — Mailhog (opcional)

Mailhog **captura** e-mails, não envia. O env atual está com `MAILER_DSN=null://null`, então o backend não usa Mailhog hoje — você pode pular este passo.

Se quiser subir mesmo assim:

```bash
kubectl apply -f kubernetes/mailhog-deployment.yaml
```

---

## Fase 8 — Carregar schema do banco

> ⚠️ O banco `supp-db-hml.postgres.database.azure.com` é compartilhado com HML. Antes de rodar qualquer migration/fixture, confirme se o schema já existe ou se é primeiro deploy.

### Verificar o estado atual das migrations

```bash
kubectl -n supp-atendimento exec deploy/backend -- \
  sh -c "cp /env-prod/.env.prod /var/www/html/.env.local && \
         php /var/www/html/bin/console doctrine:migrations:status --env=prod"
```

### Opção A — Primeiro deploy, banco vazio: seed-job completo

Aplica migrations **e** carrega fixtures (6 usuários do `Readme.md`).

```bash
kubectl apply -f kubernetes/seed-job.yaml
kubectl -n supp-atendimento wait --for=condition=complete --timeout=300s job/seed-fixtures
kubectl -n supp-atendimento logs job/seed-fixtures
```

### Opção B — Banco já tem dados: só migrations (sem fixtures)

```bash
kubectl -n supp-atendimento exec deploy/backend -- \
  sh -c "cp /env-prod/.env.prod /var/www/html/.env.local && \
         php /var/www/html/bin/console doctrine:migrations:migrate \
         --no-interaction --allow-no-migration --env=prod"
```

### Opção C — Sem certeza: migration por migration

```bash
kubectl -n supp-atendimento exec -it deploy/backend -- bash
# Dentro do container:
cp /env-prod/.env.prod /var/www/html/.env.local
cd /var/www/html
php bin/console doctrine:migrations:list --env=prod
php bin/console doctrine:migrations:execute 'DoctrineMigrations\Version20260527100000' --up --env=prod
```

---

## Fase 9 — Ingress

```bash
kubectl apply -f kubernetes/ingress.yaml

kubectl -n supp-atendimento get ingress supp-atendimento-ingress
# Esperado: ADDRESS = 20.201.103.122 (em até 30s)
```

---

## Fase 10 — Validação end-to-end

### 10.1 Tudo em Running?

```bash
kubectl -n supp-atendimento get pods,svc,ingress,secrets
```

Esperado:

```
pod/backend-xxx        1/1   Running
pod/webserver-xxx      1/1   Running
pod/mailhog-xxx        1/1   Running   (se subiu)
service/backend        ClusterIP   ...   9000/TCP
service/webserver      ClusterIP   ...   80/TCP
ingress/...            traefik    helpdesk.pgmbh.org   20.201.103.122
```

### 10.2 Testar sem depender do DNS público

```bash
# Tráfego interno (resolve dentro do cluster)
kubectl -n supp-atendimento run curl-test --rm -i --image=curlimages/curl --restart=Never -- \
  curl -sS http://webserver/ -o /dev/null -w "HTTP %{http_code}\n"

# Externo via IP do Traefik com Host header
curl -kI https://20.201.103.122 -H "Host: helpdesk.pgmbh.org"
# Esperado: HTTP/2 200 ou 302
```

### 10.3 Testar pelo DNS (se já propagou)

```bash
curl -I https://helpdesk.pgmbh.org
# Abra no navegador para confirmar o frontend Vue
```

### 10.4 Testar login via API

Se rodou Opção A na Fase 8:

```bash
curl -X POST https://helpdesk.pgmbh.org/api/login_check \
  -H "Content-Type: application/json" \
  -d '{"username":"rafael.assumpcao@pbh.gov.br","password":"<senha-da-fixture>"}'
```

A senha vem do fixture — veja `supp-atendimento-back-end/src/DataFixtures/`.

---

## Troubleshooting

| Sintoma | Diagnóstico | Solução |
|---|---|---|
| `ImagePullBackOff` | `kubectl describe pod` | Anexar ACR ao AKS: `az aks update --attach-acr suppregistry` |
| `CrashLoopBackOff` no backend, log "Unable to load private key" | Passphrase divergente | Recriar secret JWT com a mesma passphrase do env |
| `502 Bad Gateway` no webserver | Backend não pronto | `kubectl logs deploy/backend`; checar `tcpSocket:9000` |
| `503 Service Unavailable` no ingress | DNS resolveu mas service indisponível | `kubectl get endpoints -n supp-atendimento` |
| Erro de TLS handshake | Cert expirado / host errado | `openssl x509 -in cert -noout -dates -subject` |
| Login retorna 401 | Usuários não criados | Confirmar Fase 8 Opção A (fixtures) |
| Tabelas não existem | Migrations não rodaram | Fase 8 Opção B manual |
| Pod fica `Pending` | Sem nó com recursos | `kubectl describe pod` para ver evento de scheduling |

---

## Rollback completo

```bash
kubectl delete namespace supp-atendimento
# Apaga tudo em cascata: pods, deployments, services, secrets, ingress, jobs
# OBS: dados no Postgres NÃO são apagados (banco é externo ao cluster)
```

Para limpar também as tabelas:

```sql
-- psql conectado em supp-db-hml:
DROP SCHEMA public CASCADE;
CREATE SCHEMA public;
```

---

## Pós-deploy — pendências de higiene

1. **Trocar senha do Postgres** — está em texto plano em `kubernetes/env-secret.yaml` (linha 9). Versionada no git.
2. **Configurar SMTP real** — substituir `MAILER_DSN=null://null` por relay (Google Workspace / SendGrid / SMTP corporativo).
3. **Subir réplicas** — `replicas: 1` em ambos deployments = zero HA. Subir para 2 quando estável.
4. **HPA** — adicionar HorizontalPodAutoscaler nos moldes dos outros apps PROD (referência: `supp-deploy-cicd/kubernetes/php-prod/`).
5. **Resource limits do backend** — `limits.memory: 1Gi` pode ficar apertado conforme o uso real.
6. **Corrigir docker-compose.yml** — linha 78 usa imagem `supp-backend-kube` no serviço `webserver` (provável bug de copy-paste).

---

## Resumo rápido — sequência ideal sem erros

```bash
# Contexto
kubectl config use-context aks-supp-prod

# Verificações
dig +short helpdesk.pgmbh.org      # = 20.201.103.122 ?

# Chaves JWT (uma vez)
PASSPHRASE="aa75c85dfc63d2584dd56a47881d521f1399480d8143fe75e1ba8f915e2348b0"
mkdir -p /tmp/jwt-supp-atendimento && cd /tmp/jwt-supp-atendimento
openssl genpkey -out private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096 -pass pass:"$PASSPHRASE"
openssl pkey -in private.pem -out public.pem -pubout -passin pass:"$PASSPHRASE"

# Cluster
cd /supp-core/supp-atendimento
kubectl create namespace supp-atendimento

kubectl apply -f kubernetes/env-secret.yaml
kubectl create secret generic supp-atendimento-jwt \
  --from-file=private.pem=/tmp/jwt-supp-atendimento/private.pem \
  --from-file=public.pem=/tmp/jwt-supp-atendimento/public.pem \
  -n supp-atendimento
kubectl create secret tls supp-pgmbh-tls-secret \
  --cert=certificado-supp/supp-pgmbh-certificado.crt \
  --key=certificado-supp/supp-pgmbh-chave.key \
  -n supp-atendimento

kubectl apply -f kubernetes/backend-deployment.yaml
kubectl apply -f kubernetes/backend-service.yaml
kubectl apply -f kubernetes/webserver-deployment.yaml
kubectl apply -f kubernetes/webserver-service.yaml
# (opcional) kubectl apply -f kubernetes/mailhog-deployment.yaml

kubectl -n supp-atendimento rollout status deploy/backend
kubectl -n supp-atendimento rollout status deploy/webserver

# Migrations (escolher A, B ou C — ver Fase 8)
kubectl apply -f kubernetes/seed-job.yaml   # opção A

kubectl apply -f kubernetes/ingress.yaml

# Validação
kubectl -n supp-atendimento get pods,svc,ingress
curl -kI https://20.201.103.122 -H "Host: helpdesk.pgmbh.org"
```
