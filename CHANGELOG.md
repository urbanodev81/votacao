# Changelog

## [Não lançado]

### Adicionado
- Núcleo da votação (17/10/2026): votações, opções, participações, contagens e
  votos (só modo aberto); perfis enquete, interna e formal, com o formal travando
  os eixos; ações abrir, registrar voto, encerrar e apurar, com a regra de
  visibilidade no servidor.
- Voto secreto só em contadores, regravados no encerramento para apagar o rastro
  de `xmin` do último eleitor de cada opção — provado em teste contra Postgres.
- Suíte de invariantes em SQLite e Postgres (`scripts/testar.sh`).
