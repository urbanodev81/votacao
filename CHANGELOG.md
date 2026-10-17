# Changelog

## [Não lançado]

## [0.1.0] - 2026-10-17

### Adicionado
- Núcleo da votação (17/10/2026): votações, opções, participações, contagens e
  votos (só modo aberto); perfis enquete, interna e formal, com o formal travando
  os eixos; ações abrir, registrar voto, encerrar e apurar, com a regra de
  visibilidade no servidor.
- Voto secreto só em contadores, regravados no encerramento para apagar o rastro
  de `xmin` do último eleitor de cada opção — provado em teste contra Postgres.
- Suíte de invariantes em SQLite e Postgres (`scripts/testar.sh`).
- Manifestações e moderação (17/10/2026): `Manifestar` (sempre pendente, aceite
  obrigatório, "mostrar meu voto" só no aberto com uma escolha, anônima no
  secreto), `Moderar` (aprovar/rejeitar, em lote, pulando a removida) e
  `RemoverManifestacao` pelo token entregue na hora; link de rede só de host
  permitido; filtro de palavras que só marca atenção.
- `Votacao::localizar` como porta de entrada por escopo; testes da invariante 5.
