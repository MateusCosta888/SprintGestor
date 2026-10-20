# MEMORY

<!-- Índice de lições duráveis do projeto. Manter < 130 linhas.
     Teste de entrada: "uma sessão futura ficaria grata e surpreendida por saber isto?"
     O que é derivável do código NÃO entra aqui. -->

## Lições técnicas

- **Fontes carregam depois do JS**: medir texto (typewriter min-width) só é fiável após `document.fonts.ready.then(...)` — medir com fallback dá larguras erradas e layout instável. (2026-09-26)
- **Variáveis de tema inline vencem media queries**: `applyProjectTheme` define `--accent` etc. inline no `<html>`; overrides de acessibilidade (`prefers-contrast`) precisam de `!important` em `:root`. (2026-09-26)
- **Playwright headless pode emular `prefers-reduced-motion`**: ao verificar animações, criar contexto com `reducedMotion: 'no-preference'` explícito, senão parece que a animação "não funciona". (2026-09-26)
- **`overflow:hidden` num ancestor quebra `position:sticky`** de descendentes; para canvas viewport-sized dentro de wrapper com overflow usar `position:fixed`. (2026-09-26)
- **`const` de topo (Galaxy, Warp) não fica em `window`**: verificar com `if (window.Galaxy)` falha sempre; referenciar o nome diretamente (mesmo script) ou expor explicitamente. (2026-09-27)
- **Headless estrangula rAF (~7fps) com WebGL ativo**: toda a animação deve ser baseada em tempo real (`dt`/`performance.now`), nunca em k por frame; nos testes esperar por flags lógicas (`waitForFunction(() => !Warp.busy())`) em vez de timeouts fixos. (2026-09-27)
- **Symlinks no Windows sem Developer Mode falham (error 1314)** — instalar skills por cópia de pasta, não link. (2026-09-26)
- **Instalador impeccable 4.1.0**: download do bundle dá 404; fallback é `git clone` + `bun run build:skills` + copiar `dist/qoder/.qoder/skills`. (2026-09-26)
- **Animação de estado não deve re-renderizar tudo**: `cycleTaskStatus` atualiza a linha em-place (DOM + contagens); re-render completo só com filtros ativos. (2026-09-26)
- **`file://` taints vídeo para WebGL**: `texImage2D(video)` lança "cross-origin data" quando a página abre por file:// — o glass WebGL (CTA hero) só ativa servido por http(s); em file:// cai silenciosamente no fallback CSS. Nunca testar WebGL-texura via file://. (2026-09-26)
- **Playwright headless + WebGL**: usar args `--use-gl=swiftshader` e `--autoplay-policy=no-user-gesture-required` para render e vídeo funcionarem em headless. (2026-09-26)
- **Detector converte px→em no tracking**: `letter-spacing:-1.2px` num elemento de ~17px foi reportado como "-0.07em"; grep de `-0.07em` não encontra nada. Auditar tracking em px E em, ou usar sempre `em` relativo (hero agora `-0.026em`). (2026-09-26)
- **Auth real exige servidor**: hash/verificação no JS contorna-se com devtools. Backend = PHP+MySQL no InfinityFree (`api/*.php`, PDO, bcrypt, sem Node/JWT/libsodium). Contrato de endpoints em `DEPLOY.md`; toda a rede passa por 3 chokepoints (`saveData`/`initData`/`enterApp`) → trocar de host não mexe no frontend. (2026-09-27)
- **Blob por conta + `rev` otimista**: dados = 1 JSON `MEDIUMTEXT` por conta (o app já serializa `appData` inteiro). `rev` é obrigatório porque `proj.updatedAt` é uma *string* de data local (`toLocaleDateString`), inútil para ordenar sync. Conflito → 409 → diálogo "manter os meus / carregar do servidor". (2026-09-27)
- **Sessão = bearer em header, não cookie PHP**: elimina CSRF estruturalmente e evita footgun de cookies em domínio partilhado do InfinityFree. Token de sessão guarda `sha256(token)` (rápido/indexado); só o *código de acesso* é bcrypt. `SESSION.active` tem de ser reposto no `bootAuth` após `me.php` OK (senão reload fica com token mas sem sync). (2026-09-27)
- **Flag `sprintg.seededOnly`**: distingue demo de trabalho real para decidir quando oferecer migração — o demo é indistinguível por conteúdo, logo só se oferece migrar quando a flag é limpa por um `saveData()` fora do seed (`_seeding` guard). (2026-09-27)

## Estado pendente (não é derivável do código)

- Críticas 2026-09-26 em `.impeccable/critique/`: run1 **22/40**, run2 **24/40**, run3 **28/40**. Polish pós-run3 aplicado (toolbar em grupos filters/actions com divisor hairline, brand-back com chevron+"Voltar ao início", foco+anúncio na troca de vista, atalho M nos títulos aria das cards, celebração 100% com ring-pop, barra scaleX, stripes decorativas removidas, tracking floor -0.04em). Pendente: nova crítica quando o user pedir; toolbar full-reorg (P3) adiado.
- **Mega-reformulação concluída** (plano aprovado 2026-09-26): Fases 0-5 ✓, cortina de transição home↔app ✓, run3 ✓, polish ✓. Temas de cores: futuro, não agora.
- Vídeo `background.mp4` (14MB) intocável como experiência; user tem alojamento próprio — não publicar via Qoder Sites sem pedido explícito. **Plano de alojamento: InfinityFree (PHP+MySQL)**; guia de setup em `DEPLOY.md`. Backend de contas implementado e testado contra mock local (fluxo criar conta→código→login→sync→reload→logout OK), mas **ainda não validado com PHP+MySQL real** — requer deploy para confirmar.
- **Sistema de contas (2026-09-27, plano aprovado)**: login por token de acesso persistente com hash, gate no `enterApp`, dados por conta no servidor, migração perguntada. Fora de âmbito: colaboração multi-user, 2FA, OAuth, email-recovery.

## Preferências do user

- Estética: home cinematográfica (fontes + vídeo) é intocável; o resto do site deve seguir esse padrão (por isso a reformulação).
- Movimento dinâmico e suave é prioridade declarada.
- Efeito "amenizado" > intenso; nada treme durante animação.
- Gere respostas em pt-PT.

## Referências

- **Biblioteca de assets de design:** `.qoder/references/design-refs.md` — 12 fontes catalogadas (Apple HIG, beam, Aceternity, liquidglass WebGL, impeccable, vibe-coding-toolkit, superpowers, motion.dev, reactbits, anime.js) com o que foi portado/rejeitado e como reutilizar.
- **Animação de título reescreve innerHTML**: injetar markup dentro de `.section-title` (typewriter/blur-in) é apagado pela animação; por sempre irmãos do h2 (ver `.st-line`). (2026-09-27)
- **PHP portátil para verificar o backend sem XAMPP**: `%TEMP%\php83\php.exe` (download windows.php.net, descompressor PowerShell) corre `php -l` e testes CLI de funções puras (`api/ai_lib.php` ↔ `ailib_test.php`); nunca instalar PHP no projeto. (2026-09-27)
