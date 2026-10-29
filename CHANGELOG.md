# Changelog

## [Não lançado]

## [0.3.0] - 2026-10-29

### Adicionado
- Placar por recorte (29/10/2026): `recorte` (um por votação; dois deixariam descobrir
  grupo pequeno por subtração) e `recorte_minimo` (padrão 10, mínimo 5), travados no 1º
  voto. Contadores por grupo e opção em `votacao_recortes` e `votacao_recorte_contagens`,
  com o grupo vindo da costura `ResolvedorDeRecorte` dentro da transação do voto (sem a
  costura, o voto é recusado). Grupo novo nasce com todas as opções zeradas.
- No secreto, grupo abaixo do mínimo sai em "Outros", e "Outros" pequeno puxa o menor
  grupo visível; no encerramento a fusão vai para o banco e o resto é regravado junto com
  as contagens (invariante 4). No aberto, sem mínimo. `Apuracao::$recorte` traz o placar.

## [0.2.1] - 2026-10-27

### Mudado
- Repositório público, licença MIT: o build dos sistemas baixa sem token.

## [0.2.0] - 2026-10-27

### Adicionado
- Quórum (27/10/2026): `quorum_tipo` (`nenhum`, `percentual_aptos`, `absoluto`) e
  `quorum_valor`; a apuração devolve aptos, mínimo exigido e se foi atingido, visível
  mesmo no formal antes do encerramento (conta participação, não opção).
- Voto escrito: uma opção `escrito` por votação; no secreto o texto normalizado vira
  contador em `votacao_escritos`, regravado no encerramento junto com as contagens; no
  aberto fica na linha da escolha. Os textos saem só na apuração do painel.
- Recibo no perfil formal (80 bits, `XXXX-XXXX-XXXX-XXXX`), só o hash no banco, e a ação
  `ConferirRecibo`.
- `Eleitor::porDocumento` aceita a referência do sistema para a costura de elegibilidade.

### Corrigido
- O voto escrito não usa `firstOrCreate`: o savepoint dele dava ao texto um xid vizinho
  ao da participação. Teste no Postgres cobre.

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
