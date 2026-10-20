# SprintG — Regras de Projeto

Fluxo de desenvolvimento adaptado do [Vibe Coding Toolkit](https://github.com/soumatheusgomes/vibe-coding-toolkit) e [Superpowers](https://github.com/obra/superpowers). Este ficheiro deve manter-se curto e de alto sinal.

## Diretrizes de comportamento

1. **Pensa antes de codar** — trabalho criativo (feature, componente, mudança de comportamento) começa pela skill `brainstorming`; pedidos limitados bastam 2 frases de plano + aprovação. Não atirar para a primeira interpretação plausível.
2. **Simplicidade primeiro** — a menor mudança que resolve. Nada de abstrações para requisitos hipotéticos (YAGNI).
3. **Mutações cirúrgicas** — só o pedido; não "melhorar" ficheiros vizinhos. Não apagar work alheio não confirmado.
4. **Execução orientada a objetivo** — cada tarefa tem critério de fecho observável (screenshot, medição, probe).
5. **Orquestrador, não implementador** — sessões principais planeiam e decidem; investigação e revisão vão para subagentes (waves só para leitura — com um único `index.html`, paralelismo de escrita não existe).

## Stack

- Web estático: `index.html` único (HTML + CSS + JS vanilla), sem build, sem npm, sem git.
- Assets: `background.mp4`. Fontes: Google Fonts (Instrument Serif + Inter).
- Contexto de produto: `PRODUCT.md` · Sistema visual normativo: `DESIGN.md` · Lições: `MEMORY.md`.

## Comandos canónicos

- **Abrir/verificar:** Edge headless via `playwright-core` (instalar em %TEMP%, nunca no projeto) — screenshots, probes de DOM, medições de layout.
- **Detector de design:** `.qoder/skills/impeccable/scripts/impeccable.cmd detect --json index.html`
- **Sem testes/lint:** a verificação é runtime observacional (ver `verification-before-completion`).

## Roteamento de skills

| Skill | Quando usar |
|---|---|
| `brainstorming` | Antes de qualquer trabalho criativo |
| `writing-plans` / `executing-plans` | Tarefas multi-passo com spec |
| `systematic-debugging` | Qualquer bug antes de propor fix |
| `verification-before-completion` | Antes de qualquer "está pronto" |
| `impeccable` | Qualquer mudança de interface/design |
| `requesting-code-review` | Ao fechar funcionalidade relevante |

## Convenções

- **Verificação em passes fechados**: construir → 1 ronda de inspeção batched (desktop + mobile) → 1 batch de fixes → 1 confirmação → parar. Auto-QA aberto queima orçamento.
- **Zero warnings rastreado**: problemas novos começam como nota, contam-se, e só se promove a regra rígida quando chegam a zero.
- **Idioma do produto**: pt-PT (ver varredura pendente em `MEMORY.md`).
- **Acessibilidade é piso**: `prefers-reduced-motion` / `-transparency` / `contrast` honrados em toda a animação nova.
- **Memória de duas camadas**: `MEMORY.md` (índice, teto ~130 linhas) + perguntas do tipo "uma sessão futura ficaria grata por saber isto?" — o que é derivável do código não entra.

## Learn more

- Vendors clonados em `.vendor/` (fonte das práticas; não editar).
- Skills em `.qoder/skills/` (impeccable + 15 Superpowers).
