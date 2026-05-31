# BigFlint — Appwrite Custom Fork: Build & Deploy Plan

## Visão Geral

Forkar o [appwrite/appwrite](https://github.com/appwrite/appwrite), modificar o código fonte, buildar localmente com Docker, e expor na internet usando o domínio **danielvm.net**.

Baseado no cached source (`opensrc list` → `github.com/appwrite/appwrite@1.9.x`), Appwrite é **Docker-only** — 31 serviços (MariaDB, Redis, Traefik, Swoole HTTP, WebSocket, 14 workers, CLI tasks, executor, etc). Não há Makefile, não há install.sh bare-metal. O Dockerfile multi-stage usa `appwrite/base:1.4.1` como imagem base (PHP 8.3 + Swoole 6.x pré-compilado).

---

## Estrutura do Repositório

```
local-bigflint/
├── appwrite-fork/          # teu fork clonado do appwrite/appwrite
├── compose.yml             # docker compose customizado (teu build)
├── cloudflared/            # Cloudflare Tunnel config
└── .env                    # variáveis de ambiente
```

---

## Passo 1 — Forkar e Clonar

1. Ir em [github.com/appwrite/appwrite](https://github.com/appwrite/appwrite) e clicar **Fork**
2. Clonar o fork:

```bash
cd /Users/danielvm/Developer/local-bigflint
git clone git@github.com:SEU_USUARIO/appwrite.git appwrite-fork
cd appwrite-fork
```

---

## Passo 2 — Modificar o Código

A estrutura principal de módulos está em `src/Appwrite/Platform/Modules/`:

```
src/Appwrite/Platform/Modules/
├── Account/
├── Avatars/
├── Databases/
├── Functions/
├── Storage/
├── Teams/
├── Users/
└── ...
```

Cada módulo contém:
- `Http/` — endpoints HTTP (Create.php, Get.php, Update.php, Delete.php, XList.php)
- `Workers/` — workers em background
- `Tasks/` — tarefas CLI

**Para adicionar uma feature nova**, siga o padrão existente (arquivo `Action` com `setHttpMethod`, `setHttpPath`, `inject`, `callback`).

---

## Passo 3 — Buildar Localmente

```bash
cd /Users/danielvm/Developer/local-bigflint/appwrite-fork

# Build e sobe todos os serviços
docker compose up -d --force-recreate --build

# Ver logs
docker compose logs -f appwrite
```

**Build completo** leva alguns minutos (composer install + compilação). O multi-stage Dockerfile usa `composer:2` pra instalar deps PHP e `appwrite/base:1.4.1` como runtime.

---

## Passo 4 — Expor na Internet com Cloudflare Tunnel

### Por que Cloudflare Tunnel?

- **Não precisa de VPS** — roda no seu Mac e expõe via `cloudflared`
- **TLS automático** — certificado HTTPS grátis
- **DNS integrado** — aponta `appwrite.danielvm.net` pro tunnel

### Configurar

1. Instalar cloudflared:
```bash
brew install cloudflare/cloudflare/cloudflared
```

2. Autenticar:
```bash
cloudflared tunnel login
```

3. Criar tunnel:
```bash
cloudflared tunnel create bigflint
```

4. Configurar DNS em `~/.cloudflared/config.yml`:
```yaml
tunnel: ID_DO_TUNNEL
credentials-file: /Users/danielvm/.cloudflared/ID_DO_TUNNEL.json

ingress:
  - hostname: appwrite.danielvm.net
    service: http://localhost:80
  - service: http_status:404
```

5. Apontar DNS:
```bash
cloudflared tunnel route dns bigflint appwrite.danielvm.net
```

6. Rodar tunnel:
```bash
cloudflared tunnel run bigflint
```

Agora `https://appwrite.danielvm.net` aponta pro teu Appwrite local.

---

## Passo 5 — Workflow de Desenvolvimento

```bash
# 1. Modificar código
cd appwrite-fork
vim src/Appwrite/Platform/Modules/Databases/Http/Create.php

# 2. Rebuildar APENAS o serviço appwrite (mais rápido)
docker compose build appwrite
docker compose up -d appwrite

# 3. Ver logs
docker compose logs -f appwrite

# 4. Commit e push
git add .
git commit -m "feat: minha modificação"
git push origin main
```

Para rebuildar tudo (incluindo workers):
```bash
docker compose up -d --force-recreate --build
```

---

## Recomendação de VPS (revised)

**Se quiser rodar 24/7 sem depender do Mac ligado**, a recomendação muda:

| Provedor | Plano | vCPU | RAM | Preço | DC Brasil? | Ideal pra buildar? |
|---|---|---|---|---|---|---|
| **Contabo** | Cloud VPS 10 | **4** | 8GB | **~R$27/mês** (€4.50) | ❌ | ✅ **4 vCPU ajuda na build** |
| **Hostinger** | KVM 2 | 2 | 8GB | **~R$54/mês** promo | ✅ São Paulo | ⚠️ 2 vCPU pode ser lento |
| **DigitalOcean** | Basic 8GB | 2 | 8GB | ~R$72/mês | ✅ São Paulo | ⚠️ 2 vCPU |
| **HostGator BR** | VPS Médio | 2-4 | 8GB | ~R$100-130/mês | ✅ São Paulo | ✅ |

**Minha recomendação: Contabo Cloud VPS 10 (~R$27/mês)**

| Prós | Contras |
|------|---------|
| 4 vCPU agiliza build do Docker | Sem DC no Brasil (~180ms latência) |
| Preço imbatível (~R$27/mês) | Setup fee no plano mensal |
| Unlimited traffic | Suporte em inglês |
| Pode instalar Coolify pra gerenciar Docker | |

É o melhor custo-benefício pra **quem vai buildar do source frequentemente** porque 4 vCPU vs 2 vCPU faz diferença enorme no `docker compose build`.

**Alternativa brasileira:** Hostinger KVM 2 se a latência for crítica pra você. Mas prepare-se para builds mais demorados.

---

## Comandos Úteis

```bash
# Ver status dos serviços
docker compose ps

# Ver logs em tempo real
docker compose logs -f

# Acessar container appwrite
docker compose exec appwrite sh

# Rodar testes unitários
docker compose exec appwrite test tests/unit/

# Rodar CLI do Appwrite (criar projeto, usuário, etc)
docker compose exec appwrite php app/cli.php
```

---

## Resumo do Fluxo

1. Forkar → clonar → modificar `src/Appwrite/Platform/Modules/`
2. Buildar com `docker compose up -d --build`
3. `cloudflared tunnel run` pra expor em `appwrite.danielvm.net`
4. Opcional: subir pra VPS Contabo quando quiser 24/7

O ciclo de desenvolvimento fica: **edita → build → testa → repete** — tudo com Docker, sem depender de serviço externo.
