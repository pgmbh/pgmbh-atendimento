
# Para realizar Build do Projeto:
docker build -t suppregistry.azurecr.io/supp-atendimento/supp-backend-kubernetes:1.0.1 -f ./docker/php/Dockerfile .
docker build -t suppregistry.azurecr.io/supp-atendimento/supp-webserver-kubernetes:1.0.1 -f ./docker/nginx/Dockerfile .

# Enviar as imagems para azure:
docker push suppregistry.azurecr.io/supp-atendimento/supp-backend-kubernetes:1.0.1
docker push suppregistry.azurecr.io/supp-atendimento/supp-webserver-kubernetes:1.0.1


# Criar namespace do supp-atendimento:
kubectl create namespace supp-atendimento

# Executar para kubernetes:
kubectl apply -f kubernetes/backend-deployment.yaml -n supp-atendimento && sleep 10 && \
kubectl apply -f kubernetes/backend-service.yaml -n supp-atendimento && sleep 10 && \
kubectl apply -f kubernetes/mailhog-deployment.yaml -n supp-atendimento && sleep 10 && \
kubectl apply -f kubernetes/webserver-deployment.yaml -n supp-atendimento && sleep 10 && \
kubectl apply -f kubernetes/webserver-service.yaml -n supp-atendimento

# Certificado TLS PGMBH
kubectl create secret tls supp-pgmbh-tls-secret \
  --namespace supp-atendimento \
  --cert=certificado-supp/supp-pgmbh-certificado.crt \
  --key=certificado-supp/supp-pgmbh-chave.key
secret/supp-pgmbh-tls-secret created

# Ingress para criação do Host de acesso supp-atendimento.pgmbh.org
kubectl apply -f kubernetes/ingress.yaml -n supp-atendimento

# Verificar os pods:
kubectl get pods -n supp-atendimento
kubectl get ingress -n supp-atendimento


# Deletar todos os serviços: 
kubectl delete -f kubernetes/backend-deployment.yaml -n helpdesk
kubectl delete -f kubernetes/mailhog-deployment.yaml -n helpdesk
kubectl delete -f kubernetes/webserver-deployment.yaml -n helpdesk
kubectl delete -f kubernetes/webserver-service.yaml -n helpdesk
kubectl delete -f kubernetes/ingress.yaml -n helpdesk


  ┌─────┬──────────────────────────────┬─────────────────────────────┬───────────────────────┬────────┐
  │ id  │             nome             │            email            │        função         │ setor  │
  ├─────┼──────────────────────────────┼─────────────────────────────┼───────────────────────┼────────┤
  │ 3   │ Rafael Assumpcao de Oliveira │ rafael.assumpcao@pbh.gov.br │ Admin                 │ Admin  │
  ├─────┼──────────────────────────────┼─────────────────────────────┼───────────────────────┼────────┤
  │ 4   │ Lucas Almeida                │ lucas.almeida@pbh.gov.br    │ Programador Backend   │ Dev    │
  ├─────┼──────────────────────────────┼─────────────────────────────┼───────────────────────┼────────┤
  │ 5   │ Mariana Silva                │ mariana.silva@pbh.gov.br    │ Programador DevOps    │ DevOps │
  ├─────┼──────────────────────────────┼─────────────────────────────┼───────────────────────┼────────┤
  │ 6   │ Pedro Henrique Costa         │ pedro.costa@pbh.gov.br      │ Analista de Sistemas  │ Dev    │
  ├─────┼──────────────────────────────┼─────────────────────────────┼───────────────────────┼────────┤
  │ 7   │ Juliana Ferreira             │ juliana.ferreira@pbh.gov.br │ Analista de Suporte   │ Infra  │
  ├─────┼──────────────────────────────┼─────────────────────────────┼───────────────────────┼────────┤
  │ 8   │ Bruno Cardoso                │ bruno.cardoso@pbh.gov.br    │ Arquiteto de Software │ Dev    │
  └─────┴──────────────────────────────┴─────────────────────────────┴───────────────────────┴────────┘



Para produção:
  # Banco já existente — só importa o que falta:
  php bin/console doctrine:fixtures:load --group=roadmap --append
  
  # Setup completo do zero:
  php bin/console doctrine:fixtures:load