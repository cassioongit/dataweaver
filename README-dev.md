# DataWeaver — Guia de Desenvolvimento e Deploy

> Documento de referência técnica para desenvolvimento local e deploy em produção.
> Última atualização: Maio/2026

---

## Índice

1. [Ambiente de Desenvolvimento](#1-ambiente-de-desenvolvimento)
2. [Estrutura do Projeto](#2-estrutura-do-projeto)
3. [Fluxo de Deploy](#3-fluxo-de-deploy)
4. [Servidor de Produção](#4-servidor-de-produção)
5. [Segurança e Acessos](#5-segurança-e-acessos)
6. [Permissões do Servidor](#6-permissões-do-servidor)
7. [Troubleshooting](#7-troubleshooting)

---

## 1. Ambiente de Desenvolvimento

### Requisitos

| Ferramenta | Versão | Observação |
|-----------|--------|-----------|
| Node.js | **v20.x** | Obrigatório. v25+ não funciona com Vite 6 neste projeto |
| npm | 10.x | Incluído com Node v20 |
| PHP | 8.x | Para rodar a API localmente |

### ⚠️ Node v20 — Ativação Obrigatória

Este projeto requer Node v20. O Mac pode ter outra versão como padrão. **Antes de qualquer comando `npm`, ative o Node v20:**

```bash
export PATH="/usr/local/opt/node@20/bin:$PATH"
```

Confirme a versão ativa:

```bash
node --version  # deve retornar v20.x.x
```

> **Por que não fixar globalmente?** Porque outras aplicações no mesmo Mac podem depender de versões diferentes do Node. O export manual garante isolamento por sessão de terminal.

### Instalação das Dependências

```bash
export PATH="/usr/local/opt/node@20/bin:$PATH"
npm install
```

### Rodar em Desenvolvimento

```bash
# Terminal 1 — Frontend (Vite)
export PATH="/usr/local/opt/node@20/bin:$PATH"
npm run dev

# Terminal 2 — API PHP
npm run api

# Ou ambos juntos
npm run dev:all
```

A aplicação estará disponível em: `http://127.0.0.1:5173/dataweaver/`

---

## 2. Estrutura do Projeto

```
dataweaver/
├── src/                          # Código fonte React (NÃO vai para produção)
│   ├── components/
│   │   ├── ui/                   # Componentes shadcn/ui
│   │   ├── Auth.jsx              # Tela de login
│   │   └── Footer.jsx            # Rodapé
│   ├── lib/
│   │   ├── supabase.js           # Cliente Supabase
│   │   └── utils.js              # Utilitários (cn, etc.)
│   ├── App.jsx                   # Componente raiz
│   └── main.jsx                  # Entry point
│
├── api/                          # Backend PHP
│   ├── src/                      # Endpoints PHP
│   │   ├── upload.php
│   │   ├── preview.php
│   │   ├── get-dbf-data.php
│   │   ├── get-history.php
│   │   └── utils/                # Classes auxiliares
│   ├── vendor/                   # Dependências PHP
│   │   ├── csvtodbf/             # Biblioteca conversão CSV→DBF
│   │   └── phpoffice/phpexcel/   # Biblioteca Excel legada
│   ├── database/                 # Arquivos DBF (dados de produção — NÃO commitar)
│   ├── backup/                   # Backups automáticos de DBF
│   ├── logs/                     # Logs do sistema
│   └── uploads/                  # Uploads temporários de CSV
│
├── automation/                   # Scripts de automação
│   ├── build-dist.sh             # Script de build (chamado por npm run build)
│   └── fix-deploy-permissions.sh # Corrige permissões no servidor
│
├── img/                          # Imagens estáticas
├── dist/                         # Build de produção (gerado automaticamente)
├── .env.local                    # Variáveis de ambiente (NUNCA commitar)
├── vite.config.js                # Configuração do Vite
├── package.json
└── README-dev.md                 # Este arquivo
```

### Pastas que NÃO vão para o `dist/`

- `src/` — código fonte JSX (o Vite compila para `assets/`)
- `node_modules/`
- `.env.local`
- `automation/dev.sh`, `automation/php-server/`

---

## 3. Fluxo de Deploy

### Pré-requisitos

- Node v20 ativo na sessão
- Acesso SSH ao servidor como `deploy`
- Chave SSH gerenciada pelo **1Password** (agente SSH ativo)

### Passo a Passo

**1. Ativar Node v20**
```bash
export PATH="/usr/local/opt/node@20/bin:$PATH"
node --version  # confirmar v20.x.x
```

**2. Gerar o build de produção**
```bash
npm run build
```

O script `automation/build-dist.sh` vai:
- Apagar o `dist/` anterior
- Compilar o React via Vite
- Copiar `api/`, `img/` e `automation/fix-deploy-permissions.sh`
- Validar que os arquivos essenciais estão presentes

Resultado esperado:
```
vite v6.4.x building for production...
✓ N modules transformed.
Build package ready in dist/
```

**3. Zipar o build**
```bash
cd dist && zip -r ../dataweaver-dist.zip . && cd ..
```

**4. Upload para o servidor**
```bash
scp dataweaver-dist.zip deploy@157.245.213.5:/var/www/html/dataweaver/
```

O 1Password vai pedir aprovação da chave SSH — aprove.

**5. Conectar no servidor**
```bash
ssh deploy@157.245.213.5
```

**6. Limpar arquivos antigos e descompactar**
```bash
cd /var/www/html/dataweaver

# Limpar arquivos antigos (preserva dados de produção)
find . -not -name "*.zip" -not -name ".env.local" \
  -not -path "./api/database/*" \
  -not -path "./api/logs/*" \
  -not -path "./api/uploads/*" \
  -not -path "./api/backup/*" \
  -not -name "." -delete 2>/dev/null

# Descompactar novo build
unzip -o dataweaver-dist.zip

# Remover zip
rm dataweaver-dist.zip
```

**7. Verificar permissões (normalmente não necessário)**

Ao subir como `deploy` com SGID configurado, as permissões são aplicadas automaticamente. Só rodar se houver problema:

```bash
sudo chmod 640 /var/www/html/dataweaver/.env.local
sudo chmod -R 775 /var/www/html/dataweaver/api/logs \
  /var/www/html/dataweaver/api/uploads \
  /var/www/html/dataweaver/api/database \
  /var/www/html/dataweaver/api/backup
```

---

## 4. Servidor de Produção

### Informações Gerais

| Item | Valor |
|------|-------|
| Provedor | DigitalOcean |
| IP | `157.245.213.5` |
| OS | Ubuntu 24.04.4 LTS |
| Servidor Web | Apache 2.4.58 |
| PHP | 8.x |
| Domínio | `bd.dataweaverapp.com.br` |
| Path da aplicação | `/var/www/html/dataweaver/` |

### Estrutura no Servidor

```
/var/www/html/dataweaver/
├── index.html              # Frontend compilado
├── assets/                 # JS e CSS compilados pelo Vite
├── img/                    # Imagens estáticas
├── .env.local              # Credenciais (640 — protegido)
└── api/
    ├── src/                # Endpoints PHP
    ├── vendor/             # Dependências PHP
    ├── assets/             # CSS/JS legados
    ├── database/           # DBF de produção (775 — Apache escreve)
    ├── backup/             # Backups de DBF (775)
    ├── logs/               # Logs do sistema (775)
    └── uploads/            # Uploads de CSV (775)
```

### Logs do Apache

```bash
# Erros
sudo tail -50 /var/log/apache2/error.log

# Acessos
sudo tail -50 /var/log/apache2/access.log
```

### Reiniciar Apache

```bash
sudo systemctl restart apache2
```

---

## 5. Segurança e Acessos

### Acesso SSH

| Item | Valor |
|------|-------|
| Usuário | `deploy` |
| Método | Chave SSH ED25519 |
| Gerenciamento da chave | **1Password** (agente SSH) |
| Login root | **Desabilitado** |

### Conectar ao servidor

```bash
ssh deploy@157.245.213.5
```

O 1Password gerencia a autenticação automaticamente.

### Console de Emergência

Se o SSH falhar completamente, acesse o servidor via **console web do DigitalOcean**:
1. Login em digitalocean.com
2. Droplet → aba "Console"
3. Login com usuário `deploy` + senha (guardada no 1Password)

### Supabase

- A aplicação usa Supabase como backend de autenticação e dados
- Credenciais estão no `.env.local` do servidor
- **Atenção:** o plano gratuito do Supabase pausa projetos após 1 semana sem uso
- Para reativar: supabase.com → projeto DataWeaver → "Restore project"

---

## 6. Permissões do Servidor

### Modelo de Permissões

| Tipo | Permissão | Motivo |
|------|-----------|--------|
| Diretórios gerais | `755` + SGID | Apache lê, novos arquivos herdam grupo |
| Arquivos gerais | `644` | Apache lê, deploy escreve |
| Diretórios de escrita | `775` + SGID | Apache lê e escreve |
| `.env.local` | `640` | Só deploy e Apache leem, outros não |

### Diretórios com permissão de escrita (775)

- `api/database/` — DBF gravados pelo PHP
- `api/backup/` — backups automáticos
- `api/logs/` — logs do sistema
- `api/uploads/` — uploads temporários de CSV

### Usuários relevantes

| Usuário | Função |
|---------|--------|
| `deploy` | Dono dos arquivos, faz deploys |
| `www-data` | Usuário do Apache, lê e escreve onde permitido |

### Bit SGID

Todos os diretórios têm o bit SGID ativo (`drwxr-sr-x`). Isso garante que qualquer arquivo novo criado dentro do projeto herda automaticamente o grupo `www-data`, independente de quem criou.

---

## 7. Troubleshooting

### Tela branca no site

1. Verificar log do Apache:
```bash
sudo tail -50 /var/log/apache2/error.log
```

2. Verificar se o Supabase está ativo (plano gratuito pausa após inatividade)

3. Verificar console do navegador (F12 → Console) para erros de JavaScript

### Build falha com erro de Node

```bash
# Verificar versão ativa
node --version

# Se não for v20, ativar:
export PATH="/usr/local/opt/node@20/bin:$PATH"
node --version  # deve ser v20.x.x
```

### Build trava durante `transforming`

O Vite precisa de memória suficiente. Feche aplicativos pesados (Chrome, etc.) e rode:

```bash
vm_stat | grep "Pages free"  # verificar memória disponível
npm run build
```

Se ainda travar:
```bash
NODE_OPTIONS=--max-old-space-size=4096 npm run build
```

### Permissões quebradas após deploy

```bash
# No servidor, como deploy:
sudo bash /var/www/html/dataweaver/automation/fix-deploy-permissions.sh
```

### Arquivos .DS_Store no servidor

O macOS cria arquivos `.DS_Store` que não devem ir para o servidor. Para remover:

```bash
# No servidor
sudo find /var/www/html/dataweaver -name ".DS_Store" -type f -delete
```

Para evitar no Forklift: nas preferências de upload, adicione `.DS_Store` à lista de exclusões.

---

*Documento gerado em Maio/2026. Manter atualizado a cada mudança significativa na infraestrutura.*
