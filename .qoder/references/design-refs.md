# Biblioteca de Assets de Design — SprintG

Catálogo de todas as fontes de design usadas no projeto, para consulta em trabalho futuro. Cada entrada: o que é → o que já foi portado → como reutilizar → o que foi rejeitado.

---

## Portado / instalado (vivo no projeto)

### 1. libraries.dev/beam — Border Beam
- **O que:** cometa de luz que percorre a borda de cards/botões (demo React `border-beam`).
- **Portado:** sistema `.beam` em CSS puro — `@property --beam-angle` + conic-gradient mascarado + camada glow, 3 modos por projeto (discreto/sempre/off).
- **Reutilizar:** `<span class="beam" style="--beam-color:X;--beam-speed:Ns">` em qualquer pai relative+overflow:hidden.
- **Rejeitado:** a lib React (sem build no projeto).

### 2. Apple HIG /materials — Liquid Glass
- **O que:** camada funcional translúcida (regular = legibilidade, clear = sobre mídia), refração de borda, uso contido.
- **Portado:** `.liquid-glass` / `.liquid-glass-clear` (blur+satur+brightness, specular insets, anel gradiente), filtro SVG `#lg-distort` (displacement eased), modais como variante regular.
- **Reutilizar:** vidro só na camada funcional; conteúdo fica sólido. Fallbacks reduced-transparency/contrast obrigatórios.
- **Rejeitado:** vidro em painéis de dados.

### 3. Apple HIG /foundations — Tipografia, Cor, Layout, Movimento, Feedback
- **Portado:** piso 11px/sem pesos finos, `text-wrap:balance`, curva de sheet `cubic-bezier(0.32,0.72,0,1)`, `:active` 0.96/60ms, `prefers-contrast`, safe areas, One Voice Rule.
- **Reutilizar:** qualquer animação nova segue estas regras; ver DESIGN.md.

### 4. ui.aceternity.com/components — catálogo geral
- **Portado:** Spotlight Card (glow segue cursor), Shimmer Button, Bento Grid, Border Beam, Sparkles (amenizado, canvas).
- **Reutilizar:** código no próprio index.html procura por `.spotlight-card`, `.shimmer-btn`, `.bento-stat`, `#sparkleCanvas`.
- **Rejeitado:** Magic Bento com 3D tilt total, Navbar 3D (registo errado para consola).

### 5. Aceternity — Typewriter text-animation block
- **Portado:** `#typewriter` no hero — escreve/apaga 5 frases, caret segue a última letra, blur-in por letra (14px→0, arrastado), caixa por frase para layout estável.

### 6. github.com/ybouane/liquidglass — WebGL
- **Portado:** CTA do hero — canvas WebGL com vídeo como textura, SDF bevel biconvex, refração IOR 1.5, aberração cromática, fresnel, specular 2 luzes, blur por downsample. Fallback silencioso em file:// (taint) e reduced-motion.
- **Reutilizar:** padrão "textura = elemento de fundo real" para outros elementos sobre o vídeo.

### 7. impeccable.style — skill de design para agentes
- **Instalado:** `.qoder/skills/impeccable` (v4.4.0 via clone+build). Comandos: init/document/critique/audit/polish/bolder/clarify/harden/animate/typeset/adapt/optimize/live.
- **Uso:** qualquer mudança de interface passa por aqui; detector CLI `scripts/impeccable.cmd detect --json index.html`.

### 8. github.com/soumatheusgomes/vibe-coding-toolkit — metodologia
- **Destilado em `AGENTS.md`:** pensar antes de codar, verificação em passes fechados, zero-warnings rastreado, memória de duas camadas, orquestrador-não-implementador.
- **Rejeitado:** gates ESLint/350-linhas (fight com single-file), git worktrees (sem repo), Obsidian vault.

### 9. github.com/obra/superpowers — 15 skills
- **Instalado:** `.qoder/skills/` (brainstorming, writing-plans, executing-plans, systematic-debugging, verification-before-completion, TDD, code-review, subagent-driven-development...).
- **Uso:** roteamento em AGENTS.md.

---

## A usar (Fase 3 da reformulação)

### 10. motion.dev/examples — galeria Motion (456 exemplos)
- **Ouro:** scroll() + spring() para costuras de scroll; inView()+stagger(); magnetic buttons; page transitions com View Transitions.
- **Plano:** Fases 3/4 usam springs e stagger; motion via CDN ESM é alternativa se anime.js não chegar.
- **Rejeitado:** kits React/Vue, confetti/warp/curtains (registo), Motion+ (paywall: Cursor, AnimateNumber, splitText).

### 11. reactbits.dev — ~215 componentes animados
- **Top picks a portar (vanilla):** SplitText (títulos de sprint), ClickSpark (fagulhas no clique), Magnet (CTAs), CountUp/Counter (stats), TiltedCard+SpotlightCard (já temos spotlight; tilt subtil em cards).
- **Rejeitado:** backgrounds WebGL (Aurora/Silk/Particles/Beams — duplicam o nosso starfield/sparkles e lutam com o vídeo), GlassSurface/StarBorder (já temos melhor), SplashCursor (gimmick).
- **Nota:** tudo é React+GSAP/Framer → portar a técnica, nunca copiar o componente.

### 12. animejs.com — anime.js v4 (motor de movimento)
- **API a usar:** `animate()` com `spring({stiffness,damping})`, `stagger(n,{from:'center'})`, `draggable()` com snap (Kanban), timelines para sequência modal→lista→contadores, `createScope()` por componente.
- **CDN:** `import { animate, stagger, draggable } from 'https://cdn.jsdelivr.net/npm/animejs@4.5.0/+esm'` (~40KB gzip, sem build). Feature-detect com fallback CSS.
- **Não usar para:** loops ambientes (beams/sparkles/typewriter/WebGL CTA) — canvas/rAF próprios já os servem.

---

### 13. View Transitions API — page transitions SPA
- **Fontes:** https://web.dev/learn/css/view-transitions-spas · https://developer.chrome.com/docs/web-platform/view-transitions · https://piccalil.li/blog/some-practical-examples-of-view-transitions-to-elevate-your-ui/
- **Técnica:** `document.startViewTransition(() => alternar classes)` + keyframes em `::view-transition-old/new(root)` para slide+blur direcionais; classe `vt-reverse` para o caminho inverso; fallback por classe de animação quando indisponível.
- **Usado no SprintG:** primeira versão da transição home↔app (substituída pela cortina abaixo por ser "rápida e seca").

### 14. Awwwards Transitions Collection + Barba/GSAP — cortinas cinematográficas
- **Fontes:** https://www.awwwards.com/awwwards/collections/transitions/ · https://tympanus.net/codrops/2026-04-08/creating-custom-page-transitions-in-astro-with-barba-js-and-gsap/ · https://cydstumpel.nl/a-practical-guide-to-the-css-view-transition-api/
- **Padrão dominante nos nomeados** (Koto, Garden Eight, makemepulse, HOLOGRAPHIK, Cyd Stumpel): **curtain wipe com painéis escalonados** — 4-6 tiras que cobrem o ecrã em cascata com easing expo (~0.8s), a troca de conteúdo acontece atrás delas, as tiras saem pela outra extremidade; o novo conteúdo entra com rise+blur subtil enquanto as tiras saem. Total ~1.4-1.6s — "cinematográfico", não "seco".
- **Usado no SprintG:** `.page-curtain` com 5 tiras, entrada por baixo / saída por cima (inverso ao sair), troca a ~680ms, easing `cubic-bezier(0.83,0,0.17,1)`; instantâneo sob reduced-motion.
- **Barba.js** é a versão multi-página disto; em SPA de ficheiro único orquestramos à mão (sem dependência).

### 15. Orbit poster prompt (Higgsfield/Mux) — morph-reveal + rotação de vídeos
- **O que é:** prompt de poster full-viewport com rasto orgânico de rato que recorta uma imagem e revela outra; 3 vídeos de fundo (CloudFront MP4 ×2, Mux HLS ×1).
- **Técnica-chave (documentada para futuro):** canvas máscara (0.4× escala) com blobs de 24 pontos + ruído senoidal (amp 44), trail de 60 pontos com decaimento 0.92, amostra a cada 8px, cabeça raio 140 com lerp 0.14/0.04; duas máscaras `mask-image: url(canvas.toDataURL())` — frente `destination-out` (buracos), topo só-rasto.
- **Portado ao SprintG (apenas o que ficou):** `homeRotation` IIFE — pool de 3 vídeos (cosmos local, studio âmbar, bloom dourado), troca aleatória sem repetir o anterior (`localStorage sprintg.homeBg`), paleta por fundo aplicada como vars CSS só no `.hero-container` (não contamina o painel); posters gerados com ffmpeg (poster-studio/bloom.jpg); mobile/reduced-motion = poster.
- **Rejeitado pelo user:** vídeo chrome (Mux/HLS) e o especial lírio morph-reveal — removidos do código e dos ficheiros; o port do trail fica aqui documentado caso volte a ser pedido. Fontes embutidas/layout Orbit também nunca entraram.

### 16. reactbits.dev/components/flex-carousel — seletor de projetos
- **O que é:** carrossel coverflow 3D (framer-motion): card central plano e grande, laterais rodados em Y, recuados e desfocados; arrastar/setas/teclado para navegar, clique num lateral centra-o, clique no central ativa.
- **Port vanilla (sem React):** `pickerUI` IIFE — `#pickerView` (vista fixa, `html.picker-open`) com track de `.pcard` posicionadas por `d = i - index`: `translateX(d·spacing) translateZ(-|d|·130) rotateY(-sign·min(|d|,1)·34°) scale(1-|d|·0.12)`, opacity/z-index/blur por distância; transição CSS `cubic-bezier(0.22,1,0.36,1)`. Drag por pointer com threshold de 60px, setas, ←/→, Enter. Cada projeto é um card (ícone, nome serif, descrição clamp, "N sprints", "Abrir"); o último card é sempre um "+ Novo projeto".
- **Fluxo:** login/`enterApp` → `enterAppFlow()` = cortina home→app + `openPicker()` (adiado ~700ms para o picker ser revelado pela cortina, não saltar por cima da home); escolher card → `selectProject()` fecha o picker e revela o painel; "Trocar" reabre. reduced-motion: sem rotação/blur, sem animação de entrada. **Drag contínuo:** posição fracionada (`pos` float), transforms recalculados a cada pointermove, transição desligada durante o arrasto (`.dragging`), snap+mola no release, borracha nas pontas e impulso de flick. **Bug a evitar:** `setPointerCapture` retargeta o `click` para o palco → cards deixam de responder; usar listeners na `window` para move/up.

### 17. reactbits.dev/backgrounds/galaxy — fundo do seletor de projetos
- **O que é:** campo de estrelas WebGL (Three.js) com rotação, twinkle, glow, deriva e repulsão do rato.
- **Port Canvas2D (sem Three.js):** `Galaxy` IIFE — ~density·área estrelas num disco uniforme (raiz de rand), rotação global lenta, halo via sprite radial branco pré-renderizado em modo `lighter` (barato) + núcleo colorido `hsl(hueShift±var, sat·100%, light)`. Twinkle por seno. **Repulsão do rato suavizada:** alvo calculado por (1−d/R)·força, mas aplicado com lerp `ox += (target−ox)·0.06` e força atenuada (×0.4) → movimento macio, não elástico. Corre só com o picker aberto (`Galaxy.start/stop` em openPicker/closePicker); reduced-motion = frame estático.
- **Preset do user:** density 1.4, glow 0.3, saturation 0.3, hueShift 140, twinkle 0.3, rotation 0.05, repulsion 4.5, starSpeed 0.6, speed 0.2.
- **Nota:** `const Galaxy` ao nível do script NÃO fica em `window`; chamar `Galaxy.start()` diretamente (closure do mesmo script), não `window.Galaxy`.

---

## Regras de ouro ao portar
1. Gate `prefers-reduced-motion` + fallback `prefers-reduced-transparency` SEMPRE.
2. Portar a técnica, não o componente (React→vanilla).
3. Verificar com screenshot/probe no browser — nunca declarar sem evidência.
4. Um momento autoritário por superfície; não empilhar efeitos no mesmo elemento.
