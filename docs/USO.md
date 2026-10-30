# Guia de uso

[README](../README.md) · Veja também: [Desenvolvimento e testes](DESENVOLVIMENTO.md) · [Automação do repositório](AUTOMACAO.md)

Extensão SQLite para o framework Elavora, baseada em `elavora/api-database-pdo`.

Este pacote só traduz a configuração SQLite (`path` ou `memory`) para o DSN do PDO e delega o resto ao
`elavora/api-database-pdo`: a fábrica de conexões, o helper `PdoDatabase` e as regras de consulta estão no
[guia do pdo](https://github.com/Elavora/api-database-pdo/blob/main/docs/USO.md).

## Versão e compatibilidade

| Item | Valor |
| --- | --- |
| Versão deste pacote | **1.0.0** (sai do PR com label `upgrade`, que sobe a major a partir da `v0.2.0`) |
| Restrição que os dependentes devem usar | `"elavora/api-database-sqlite": "^1.0"` |
| `elavora/api-framework` exigido | `^2.0` |
| `elavora/api-database-pdo` exigido | `^1.0` |
| PHP exigido | `>=8.3` (o mesmo mínimo do framework 2.0) |
| Extensão exigida | `ext-pdo_sqlite` `*` |

O `composer.json` do pacote declara exatamente:

```json
"require": {
    "php": ">=8.3",
    "ext-pdo_sqlite": "*",
    "elavora/api-database-pdo": "^1.0",
    "elavora/api-framework": "^2.0"
}
```

e o alias da `main` é `"dev-main": "1.0-dev"` em `extra.branch-alias`.

### O que muda para quem usa

A versão 1.0.0 é uma quebra de compatibilidade só nos requisitos:

- não aceita mais PHP 8.1 nem 8.2 (era `>=8.1`);
- não aceita mais o framework `0.x` (era `^0.3.1`) e exige o `elavora/api-framework` `^2.0`;
- não aceita mais o `elavora/api-database-pdo` `0.x` (era `^0.1`) e exige o `^1.0`.

**A API pública deste pacote não muda em relação à `v0.2.0`:** a classe, o namespace, o construtor, as chaves de
configuração, as regras de validação e as mensagens de erro são os mesmos. Para migrar, atualize PHP, o framework e o
pdo; o código que registra a `SqliteExtension` continua igual. Os contratos usados (`Extension` e
`DatabaseConnectionFactory`, em `Elavora\Api\Framework\Contracts`) têm a mesma assinatura no framework 2.0.

## Instalação

```bash
composer require elavora/api-database-sqlite:^1.0
```

Requisitos: PHP 8.3 ou superior, `ext-pdo_sqlite`, `elavora/api-framework` `^2.0` e `elavora/api-database-pdo` `^1.0`.

## API pública

Namespace: `Elavora\Api\Extension\DatabaseSqlite`. Há uma classe, `final`.

### `SqliteExtension implements Extension`

```php
public function __construct(array $config)
public function register(Application $application): void
```

`Extension` é `Elavora\Api\Framework\Contracts\Extension`. A extensão é registrada com
`$application->extend(new SqliteExtension($config))`, que chama `register()` na hora.

#### Formatos de configuração

Conexão única (sem a chave `connections`):

```php
new SqliteExtension(['path' => '/var/data/app.sqlite']);   // arquivo
new SqliteExtension(['memory' => true]);                   // banco em memória
```

Várias conexões nomeadas (com a chave `connections`; o valor de cada nome tem o mesmo formato da conexão única):

```php
new SqliteExtension([
    'connections' => [
        'default' => ['path' => '/var/data/app.sqlite'],
        'analytics' => ['memory' => true],
    ],
]);
```

#### Regras de tradução para o DSN

Para a conexão única, ou para cada item de `connections`:

1. `memory` igual a `true` (estritamente booleano) produz o DSN `sqlite::memory:`. Nesse caso `path` é ignorado.
2. Senão, `path` precisa ser uma string não vazia e produz o DSN `sqlite:<path>` (o texto de `path` sem alteração).
3. Senão, lança `InvalidArgumentException` com a mensagem `A configuracao SQLite deve informar path ou memory=true.`
   Isso vale para `path` ausente, `null`, `''` ou que não seja string, e para `memory` diferente de `true`
   (por exemplo `false`, `1`, `'true'`) quando não há `path` válido.
4. Uma chave `dsn` informada pelo usuário **não é usada**: o DSN sempre é calculado a partir de `path` ou `memory`.
5. As demais chaves (`username`, `password`, `options`) são repassadas sem alteração ao `PdoConnectionFactory` do
   `elavora/api-database-pdo`, que as entrega ao construtor do PDO.
6. A chave `connections` conta como presente quando existe e não é `null`. Com ela, as chaves de conexão única na raiz
   (`path`, `memory`) são ignoradas. Cada valor de `connections` precisa ser um array, senão lança
   `InvalidArgumentException` com `Configuracao SQLite '<nome>' deve ser um array.`

Exemplos:

| Configuração | Resultado |
| --- | --- |
| `['memory' => true]` | DSN `sqlite::memory:` |
| `['memory' => true, 'path' => '/tmp/a.sqlite']` | DSN `sqlite::memory:` (`path` ignorado) |
| `['path' => '/tmp/a.sqlite']` | DSN `sqlite:/tmp/a.sqlite` |
| `['path' => '/tmp/a.sqlite', 'dsn' => 'sqlite::memory:']` | DSN `sqlite:/tmp/a.sqlite` (`dsn` ignorado) |
| `['memory' => false, 'path' => '/tmp/a.sqlite']` | DSN `sqlite:/tmp/a.sqlite` |
| `['connections' => ['a' => ['memory' => true]]]` | uma conexão nomeada `a`, em memória |
| `[]` | `InvalidArgumentException`: `A configuracao SQLite deve informar path ou memory=true.` |
| `['path' => '']` | `InvalidArgumentException`: `A configuracao SQLite deve informar path ou memory=true.` |
| `['path' => 123]` | `InvalidArgumentException`: `A configuracao SQLite deve informar path ou memory=true.` |
| `['memory' => 1]` | `InvalidArgumentException`: `A configuracao SQLite deve informar path ou memory=true.` |
| `['connections' => ['a' => 'x.sqlite']]` | `InvalidArgumentException`: `Configuracao SQLite 'a' deve ser um array.` |
| `['connections' => ['a' => []]]` | `InvalidArgumentException`: `A configuracao SQLite deve informar path ou memory=true.` |

A validação acontece em `register()`, e não ao conectar: com configuração inválida, `$application->extend(...)` já lança
a exceção. O arquivo SQLite só é aberto na primeira chamada de `connection()`.

#### O que `register()` registra

`register()` faz dois registros no container da aplicação:

| Identificador | Resolve para |
| --- | --- |
| `Elavora\Api\Framework\Contracts\DatabaseConnectionFactory` | um `Elavora\Api\Extension\DatabasePdo\PdoConnectionFactory` com a configuração já traduzida |
| `Elavora\Api\Extension\DatabasePdo\PdoDatabase` | um `PdoDatabase` sobre `connection()` (a conexão `default`) dessa fábrica |

Comportamento da fábrica (regras do `PdoConnectionFactory` do pdo 1.0):

- `connection()` e `connection('default')` devolvem o mesmo `PDO`, e cada nome abre o PDO uma vez só.
- Nomes diferentes devolvem instâncias diferentes. Em memória, cada conexão é um banco separado.
- No formato de conexão única só existe `'default'`; outro nome lança `InvalidArgumentException`
  (`Conexao PDO '<nome>' nao esta configurada.`).
- No formato `connections`, o nome precisa existir; senão a mesma exceção. Sem uma conexão chamada `default`,
  `connection()` sem nome e a resolução de `PdoDatabase` lançam essa exceção.
- Erros do próprio PDO sobem como `PDOException`.

`PdoDatabase` tem `execute`, `fetch`, `fetchAll`, `value`, `select`, `insert`, `update`, `delete` e `exists`, descritos
no guia do pdo.

#### Limite conhecido: `TransactionManager`

Diferente do `PdoExtension` do pdo, a `SqliteExtension` **não** registra o
`Elavora\Api\Framework\Contracts\TransactionManager` no container. Portanto o atributo `Transaction` do framework não
encontra o gerenciador de transação só com esta extensão. As transações manuais pelo `PdoDatabase` (`begin()`,
`commit()` e `rollback()`) funcionam normalmente. Este comportamento é o atual e não muda no 1.0.0.

## Exemplo rápido

```php
use Elavora\Api\Extension\DatabasePdo\PdoDatabase;
use Elavora\Api\Extension\DatabaseSqlite\SqliteExtension;
use Elavora\Api\Framework\Application;

$application = Application::create()->extend(new SqliteExtension([
    'path' => getenv('DB_PATH') ?: ':memory:',
]));

$database = $application->container()->get(PdoDatabase::class);
$database->execute('CREATE TABLE events (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
$database->insert('events', ['name' => 'created']);
```

O valor `':memory:'` em `path` produz o DSN `sqlite::memory:`, o mesmo efeito de `'memory' => true`.

## Desenvolvimento e testes

Os testes usam SQLite em memória, então não precisam de banco externo. O passo a passo, os scripts do Composer e o que
roda no CI estão em [Desenvolvimento e testes](DESENVOLVIMENTO.md).

## Observações

- Mantenha regras de produto fora deste pacote.
- Prefira configurar extensões no bootstrap da aplicação.
- Instale apenas os módulos que a aplicação realmente usa.
