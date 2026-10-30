# elavora/api-database-sqlite

[![Packagist Version](https://img.shields.io/packagist/v/elavora/api-database-sqlite.svg?style=flat-square)](https://packagist.org/packages/elavora/api-database-sqlite)
[![PHP Version](https://img.shields.io/packagist/php-v/elavora/api-database-sqlite.svg?style=flat-square)](https://packagist.org/packages/elavora/api-database-sqlite)
[![License](https://img.shields.io/packagist/l/elavora/api-database-sqlite.svg?style=flat-square)](LICENSE)

Extensão SQLite para o framework Elavora, baseada em `elavora/api-database-pdo`.

- Versão: **1.0.0**. Dependentes devem exigir `"elavora/api-database-sqlite": "^1.0"`.
- Exige `elavora/api-framework` `^2.0`, `elavora/api-database-pdo` `^1.0`, PHP `>=8.3` e `ext-pdo_sqlite`.

Registre `SqliteExtension` com `path` para arquivo local ou `memory` como `true` para banco em memória.
A chave `connections` habilita conexões nomeadas.

Ao registrar a extensão, o container recebe `DatabaseConnectionFactory` e `PdoDatabase`.
`PdoDatabase` tem os métodos `execute`, `fetch`, `fetchAll`, `value`, `select`, `insert`,
`update`, `delete` e `exists`.

A especificação completa, com regras e exemplos, está em [docs/USO.md](docs/USO.md).

## Instalação

```bash
composer require elavora/api-database-sqlite
```

Requisitos: PHP 8.3 ou superior, `ext-pdo_sqlite`, `elavora/api-framework` `^2.0` e `elavora/api-database-pdo` `^1.0`.

## Atualizando da 0.x

A 1.0.0 só muda os requisitos (PHP 8.3, framework 2 e pdo 1); a API do pacote é a mesma da `v0.2.0`. Veja
[O que muda para quem usa](docs/USO.md#o-que-muda-para-quem-usa).

## Documentação

1. [Guia de uso](docs/USO.md): a especificação do pacote, com a API pública e exemplos.

## Licença

MIT
