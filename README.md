# urbanodev/votacao

Votação dentro do sistema, da enquete de show à assembleia formal. É a
implementação Laravel do módulo **`kit-desenvolvimento/modulos/votacao`** — a
especificação, os invariantes e o porquê de cada decisão estão lá; aqui está o
código. Divergiu da especificação? A especificação muda primeiro.

## O que entrega (e o que não)

- Migrations, models, ações (`AbrirVotacao`, `RegistrarVoto`, `EncerrarVotacao`,
  `Apurar`, `ConferirRecibo`, `Manifestar`, `Moderar`, `RemoverManifestacao`), validação dos perfis e as interfaces das costuras.
- Quórum (`quorum_tipo` + `quorum_valor`) e voto escrito (opção com `escrito = true`; o texto é o
  4º argumento de `RegistrarVoto`). No secreto, o texto vira contador em `votacao_escritos`.
- Recibo no formal: `RegistrarVoto` devolve a participação com `->recibo` em claro, a única vez
  que ele existe; o banco guarda só o hash.
- **Não entrega tela.** Cada sistema faz a sua, com a marca dele.

## Instalar num sistema

```jsonc
// composer.json do sistema
"repositories": [{ "type": "vcs", "url": "https://github.com/urbanodev81/votacao" }],
"require": { "urbanodev/votacao": "0.1.0" }   // versão exata, nunca faixa
```

Repositório privado: o build Docker recebe o token por secret do BuildKit
(ver `porte-por-stack.md` no kit). Banco por tenant (stancl):
`VOTACAO_CARREGAR_MIGRATIONS=false` e
`php artisan vendor:publish --tag=votacao-migrations`, movendo para
`database/migrations/tenant`.

## Costuras

Ligue no service provider do sistema. A que não for ligada **recusa**.

| Interface | Quando |
|---|---|
| `Contratos\VerificadorDeHumano` | enquete por aparelho ou e-mail (Altcha) |
| `Contratos\ResolvedorDeElegibilidade` | votação com `elegibilidade` preenchida |

## Escopo (invariante 5)

Endpoint do sistema entra por `Votacao::localizar($escopo, $uuid)` — o
`$escopo` vem da autenticação ou do domínio, nunca do request — e passa a
votação achada a `Moderar` e `RemoverManifestacao`. Id ou token de outra
votação dá `ModelNotFoundException` (404).

## Testes

```bash
scripts/testar.sh   # SQLite + Postgres descartável, só precisa de Docker
```

## Estado

| Passo do kit | |
|---|---|
| 1–2 núcleo, perfil enquete, aparelho | ✅ |
| 3 manifestações e moderação | ✅ (ações; a fila em tela é de cada sistema) |
| 4 e-mail (voto pendente) | a fazer |
| 5 conta + elegibilidade | ✅ (núcleo) |
| 6 formal (documento, recibo, auditoria) | validação ✅, recibo ✅, quórum e voto escrito ✅ (v0.2.0); auditoria a fazer |
