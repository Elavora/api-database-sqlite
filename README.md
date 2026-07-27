# elavora/api-database-sqlite

Extensao opcional SQLite baseada em `elavora/api-database-pdo`.

Registre `SqliteExtension` com `path` para arquivo local ou `memory` como
`true` para banco em memoria. A chave `connections` habilita conexoes nomeadas.

O container recebe `PdoDatabase` e `TransactionManager` sobre a conexao
`default`. O pacote requer PHP 8.3 e dependencias Elavora 1.x.
