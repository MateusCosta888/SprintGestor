# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Pessoas que planeiam trabalho em sprints — criadores independentes, estrategistas e pequenas equipas — usando o SprintG como produto público acessível no browser. O utilizador chega com metas vagas e precisa de as transformar em tarefas com estado, prioridade e pontos; o primeiro contacto é a landing cinematográfica, o uso continuado é o dashboard.

## Product Purpose

Gestor de sprints completo: projetos múltiplos, sprints com meta e cor, tarefas com ciclo de estado (planeada → em curso → concluída → bloqueada), story points, prioridades, pesquisa, filtros, métricas de progresso e modelos. Sucesso = pessoas adotam-no para o planeamento real do seu trabalho e sentem que é melhor do que um Kanban comum.

## Positioning

Beleza + função: o único gestor de sprints com experiência cinematográfica (liquid glass da Apple HIG, border beams, sparkles, transições de scroll) sem sacrificar a utilidade — e com zero fricção: um ficheiro HTML, sem contas, sem servidor, funciona offline. Um concorrente pode copiar os cartões; não pode copiar "abrir um gestor de sprints e sentir um trailer de filme" mantendo a ferramenta local e instantânea.

## Operating Context

Navegador desktop-first (responsivo até 820px e safe areas móveis). Dados vivem em localStorage do browser; exportação/importação JSON é o mecanismo de backup e partilha. O utilizador pretende publicar online (tem alojamento próprio). Idioma do produto: português europeu (pt-PT).

## Capabilities and Constraints

- Ficheiro único `index.html` (HTML + CSS + JS vanilla, sem build, sem dependências além de Google Fonts); vídeo `background.mp4` local.
- Sem backend: nada de contas, colaboração em tempo real ou sincronização — até o produto escalar; não assumir servidor nos designs.
- Sistema de temas por projeto (accent, fundo, modo do beam) já é comportamento central; novas superfícies devem respeitá-lo.
- Acessibilidade ligada: `prefers-reduced-motion`, `prefers-reduced-transparency`, `prefers-contrast` implementados e a manter.
- Nome do produto é **SprintG** (alternativa formal: SprintGestor). Tagline, vídeo de fundo e tom épico são revisáveis; o nome não.
- Decisão de método de trabalho registada: code-first (ver `.impeccable/config.json`).

## Brand Commitments

Nome: SprintG / SprintGestor (vinculativo). Restante identidade visual e verbal — tagline atual "Onde visões audaciosas ganham vida", vídeo, tom — aberta a evolução quando melhorar o produto.

## Evidence on Hand

- Protótipo funcional completo com dados de exemplo (projeto "NigorOS" via botão Carregar Modelo).
- Vídeo de fundo real: `background.mp4` (14 MB).
- Não existem: depoimentos, casos de uso de terceiros, métricas de adoção, preços, licenciamento. Não fabricar nenhum destes.

## Product Principles

1. A estética é função — o design cinematográfico é razão de adoção, não decoração removível.
2. Zero atrito — abrir e usar em segundos, sem conta, sem rede, sem setup.
3. Os dados são do utilizador — local por defeito, exportáveis sempre, nunca presos.
4. Movimento com sentido e acessível — toda a animação tem desligamento por preferência do sistema.
5. Um ficheiro, tudo incluído — a simplicidade técnica é parte da promessa.

## Accessibility & Inclusion

Sem requisito formal declarado (ex.: WCAG AA), mas o produto já honra reduced motion, reduced transparency e contraste aumentado; manter e estender este piso em todo o novo trabalho de interface.
