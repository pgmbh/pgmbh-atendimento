## Ambiente de Desenvolvimento

Para executar o ambiente de desenvolvimento, que inclui sincronização de código em tempo real e configuração automática do banco de dados.

### Primeiro Uso

1.  **Instale as dependências do Composer localmente:**
    Como o código é montado a partir do seu computador (incluindo a pasta `vendor`), você precisa instalar as dependências do PHP no seu host primeiro.
    ```bash
    cd supp-atendimento-back-end
    composer install
    cd ..
    ```

### Uso Diário

Para iniciar e parar o ambiente, use os comandos:

```bash
# Para iniciar os serviços
docker-compose up -d --build
docker-compose --profile dev up -d --build


# Para parar os serviços
docker-compose down

```

# comandos para subir do clickup

# reverter
php bin/console app:revert-roadmap --force 

# adicionar
php bin/console app:import-roadmap /tmp/all_tasks.json 