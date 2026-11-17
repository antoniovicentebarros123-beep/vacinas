# Controlo de Vacinação Infantil (PWA, Cloudflare)

Sistema offline-first para postos médicos em Angola. Interface em HTML/CSS/JS puros, API em Cloudflare Workers, base central em D1, backups em R2.

**Estado: Fase 1a** (estrutura, base de dados, camada local, layout). O login (Fase 1b) aguarda a aprovação do desenho do PBKDF2.

## Requisitos
- Node.js 20 ou superior
- Conta Cloudflare para o deploy. O plano Workers Gratuito dá 10 ms de CPU por pedido, e o hash de palavras-passe provavelmente excede isso: conta Workers Paid recomendada (a confirmar por medição na Fase 1b).

## Desenvolvimento local
```bash
npm install
npm run seed:gerar         # gera database/seeds/0001_catalogo.sql (falha se não houver 15 oportunidades)
npm run db:migrar:local    # wrangler d1 migrations apply vacinacao --local
npm run db:seed:local      # carrega posto, vacinas e calendário
npm run dev                # http://localhost:8787
npm test                   # 31 testes
```
Pré-visualização do painel sem login (temporária, só em localhost): `http://localhost:8787/?previa`

## Criar a D1 na Cloudflare
```bash
npx wrangler d1 create vacinacao      # copiar o database_id para o wrangler.jsonc
npx wrangler d1 migrations apply vacinacao --remote
```

## Estrutura
- `public/`: interface estática. `public/shared/` é a fonte única de regras, importada também pelo Worker (nada de segredos aqui)
- `src/worker/`: API (router próprio, CSRF, cabeçalhos de segurança)
- `database/migrations/` SQL da D1 · `database/seeds/` calendário vacinal (IDs fixos)
- `tests/`: testes (`node --test`)

## Limites conhecidos desta fase
- Sem login, sincronização, Service Worker nem manifest (Fases 1b e 5).
- Valores clínicos do calendário que não vêm do cartão ficam `NULL` e a linha fica com `validar_clinica = 1`.
- A interface foi validada com testes em DOM simulado; falta vê-la num navegador real.
- Os navegadores não deixam a app bloquear a limpeza de dados: o sistema pede armazenamento persistente e mostra aviso enquanto houver registos por enviar.
