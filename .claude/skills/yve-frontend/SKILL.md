---
name: yve-frontend
description: Use ao criar ou alterar QUALQUER tela do YVE Agency — views PHP com Tailwind + Alpine.js. Cobre os tokens do design system (nada hardcoded), os 4 estados obrigatórios, o padrão de fetch/JS, erros comuns já cometidos neste projeto (select branco no branco, imagem sem proporção, x-cloak) e como sair do visual genérico de template admin. Aciona em "criar/ajustar tela", "melhorar o visual", "essa página está genérica", "adicionar formulário/listagem/modal", ou qualquer edição em resources/views.
---

# Frontend do YVE Agency (tokens, estados, JS e anti-genérico)

Stack: views PHP nativas + Tailwind + Alpine.js, tema **dark** com acento violeta. Sem React, sem build (ainda — FE-01 no `yve-roadmap` vai trazer Tailwind CLI). O objetivo destas regras: toda tela nova parecer parte de um **produto validado**, não de um template.

## 1. Tokens — nada hardcoded

Fonte de verdade visual (hoje duplicada nos layouts; FE-01 vai unificar — **não crie uma 5ª cópia**):

| Token | Valor | Uso |
|-------|-------|-----|
| Fundo base | `gray-950` (#09090f) | body |
| Superfície | `gray-925` (#0f1117) / `#12121a` | cards, dropdowns |
| Borda | `white/[0.06]`–`white/[0.10]` | divisões sutis |
| Texto | `gray-200` corpo · `gray-500` secundário · `white` título | |
| Acento | violeta `violet-500` (#8b5cf6) | ação primária, foco, glow |
| Estados | `emerald` sucesso · `amber` alerta · `red` erro | |
| Fonte | Inter (400–800) | |
| Raio | `rounded-lg` padrão · `rounded-xl` cards | |

Regras:
- **Nunca** cor hexadecimal solta em view (`style="color:#8b5cf6"` é proibido). Use classe Tailwind do token.
- Reutilize as classes de componente do layout: `.card`, `.btn-primary`, `.input-field`, `.label-field`. Se precisar de um componente novo, defina-o **no layout/CSS compartilhado**, não inline na view.
- Espaçamento na escala do Tailwind (`p-4`, `gap-3`…) — nunca `style="margin: 13px"`.
- Novo padrão visual recorrente (badge de status, tabela, empty-state) → vira partial em `resources/views/partials/`.

## 2. Os 4 estados — obrigatórios em toda tela de dados

1. **Loading** — skeleton ou spinner (`x-show` com flag); botão de submit desabilitado + label "Enviando…".
2. **Vazio** — nunca uma área em branco: ícone + frase + ação ("Nenhum plano ainda. Criar o primeiro").
3. **Erro** — mensagem visível e acionável (retry quando fizer sentido). `catch {}` silencioso é bug.
4. **Sucesso** — flash (`withSuccess`) ou atualização otimista visível.

## 3. JS — padrão do projeto

- Alpine para interação de tela (`x-data` com função nomeada, como `driveManager()` em `portal/files.php`).
- **Todo `fetch` valida `response.ok`** e envia `X-CSRF-Token` (meta tag `csrf-token` no layout) em mutação. Pós FE-03: usar o wrapper `public/js/api.js` — não escrever fetch cru.
- Dados do PHP para o JS: `json_encode($x, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)` — nunca interpolar string crua em `<script>`.
- `innerHTML` só com DOMPurify (ver `ia/show.php`). Preferir `textContent`/`x-text`.
- Objetos não-reativos (XHR, Chart) ficam **fora** do estado do Alpine (registry externo — o Alpine "proxyar" um XHR quebra; já aconteceu).
- View passando de ~400 linhas por causa de JS → extrair módulo para `public/js/` (FE-02).

## 4. Erros que este projeto JÁ cometeu — não repita

- **Select branco no branco:** `<option>` herda texto claro, mas o dropdown nativo tem fundo branco. Todo `<select>` precisa de `color-scheme: dark` + fundo de option escuro (já global no layout — não sobrescreva).
- **Imagem sem proporção:** `w-full max-h-64` deixa foto 3:4 virar bloco gigante. Use quadro de proporção fixa — `ContentPlanService::previewFrameClass()` (9:16 Reels/Story, 3:4 foto/carrossel, 1:1 feed).
- **Link de imagem do Drive:** `uc?export=view` está morto. Use o proxy próprio (`/…/file/{id}/raw`) para arquivo do app, ou `GoogleDriveService::imageSrc()` (thumbnail) para link colado.
- **Flash de conteúdo Alpine:** elementos interativos levam `x-cloak`.
- **Form aninhado** engole o submit (bug real do form de clientes): nunca `<form>` dentro de `<form>`.
- **Permissão só na view:** esconder botão não protege nada — a guarda é no controller (ver `yve-seguranca`).

## 5. Sair do genérico (cara de produto validado)

- **Densidade com hierarquia:** título da página curto + ação primária à direita; métricas em cards compactos com label pequena em `gray-500` e valor grande; nunca três tamanhos de fonte no mesmo card.
- **Tabelas:** cabeçalho `text-xs uppercase tracking-wide text-gray-500`; linhas com hover sutil; status como badge colorida (token de estado), não texto solto; alinhamento numérico à direita.
- **Consistência de ação:** uma única ação primária violeta por tela; secundárias em ghost/outline. Destrutivas em `red` **com confirmação que diz o impacto** ("Apagar o cliente remove X faturas e Y arquivos").
- **Microcópia em pt-BR de gente:** "Nenhum resultado para esse filtro" > "Não há dados". Datas relativas ("há 2 dias") com absoluta no `title`.
- **i18n:** toda string visível via `t('chave')` (pt/en/es em `resources/lang`) — texto hardcoded quebra o portal, que fala o idioma do cliente.
- **Acessibilidade mínima:** foco visível (o glow violeta já existe — não remova outline sem substituir), `aria-label` em botão só-ícone, contraste ≥ 4.5:1 sobre `gray-950` (gray-600 falha — use gray-500+ para texto informativo, gray-400 para secundário legível).
- **Responsivo:** sidebar colapsa; tabela larga rola em container próprio (`overflow-x-auto`), a página nunca rola horizontal; testar 375px (clientes aprovam pelo celular).

## 6. Checklist de tela (antes de dar por pronta)

```
[ ] Só tokens/classes do sistema — zero hex/px soltos, zero style= com cor
[ ] 4 estados presentes (loading/vazio/erro/sucesso)
[ ] e() em toda saída · csrf_field()/X-CSRF-Token em toda mutação
[ ] Strings via t() · testada em pt (e en se tocar no portal)
[ ] fetch valida response.ok · nada de catch{} vazio
[ ] Select/option legíveis · imagens com proporção fixa · x-cloak onde precisa
[ ] Mobile 375px ok · foco de teclado visível
[ ] Validada rodando de verdade (skill visual-validation)
```

Para dashboards/telas densas de gestão, carregue também a skill global `enterprise-ui-design`; para gráficos, `dataviz`.
