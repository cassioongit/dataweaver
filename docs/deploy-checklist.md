# Deploy Checklist

Checklist curto para quando subir uma nova versão do Dataweaver em produção.

## 1. Publicar os arquivos

Suba o conteúdo de `dist/` para a pasta pública do servidor, por exemplo:

```bash
/var/www/html/dataweaver
```

O diretório final precisa conter:

- `index.html`
- `assets/`
- `img/`
- `api/`

## 2. Corrigir permissões dos diretórios mutáveis

No servidor, rode:

```bash
sudo /var/www/html/dataweaver/automation/fix-deploy-permissions.sh /var/www/html/dataweaver
```

Se o projeto publicado não incluir a pasta `automation/`, rode o script a partir do checkout local do projeto apontando para a pasta publicada:

```bash
sudo APP_ROOT=/var/www/html/dataweaver /caminho/do/repositorio/automation/fix-deploy-permissions.sh
```

Esse script corrige owner e permissão apenas do que o PHP precisa escrever:

- `api/database`
- `api/backup`
- `api/logs`
- `api/logs/import_audits`
- `api/uploads`

## 3. Validar estrutura

Rode:

```bash
PROJECT_ROOT=/var/www/html/dataweaver /caminho/do/repositorio/automation/check-dirs.sh
```

Verifique:

- `database`, `backup`, `logs` e `uploads` existem
- os diretórios aparecem como graváveis
- `RELAT_orto.DBF` está dentro de `api/database`

## 4. Testar a API direto

Logado no sistema, teste no navegador:

- `/dataweaver/api/src/get-dbf-data.php?page=1&limit=1`
- `/dataweaver/api/src/get-history.php`

Sem autenticação, o esperado é `401`.
Com autenticação, o esperado é JSON válido.

## 5. Testar no frontend

Depois do deploy:

1. Faça hard refresh no navegador (`Ctrl+Shift+R`)
2. Entre em `DBF`
3. Entre em `Histórico`
4. Teste um download de base

## 6. Se ainda falhar

Verifique:

- owner dos arquivos em `api/database` e `api/logs`
- se o request está indo para `/dataweaver/api/...` e não para `127.0.0.1:8888`
- se `import_history.json` e `system.log` continuam sendo atualizados
