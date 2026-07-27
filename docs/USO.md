# Guia de uso

Extensao opcional SQLite baseada em `elavora/api-database-pdo`.

## Instalacao

```bash
composer require elavora/api-database-sqlite
```

## Quando usar

- Registrar conexoes de banco como extensao da aplicacao.
- Consumir contratos de banco pelo container do framework.
- Manter configuracao de DSN e credenciais fora da regra de negocio.

## Exemplo rapido

```php
use Elavora\Api\Extension\DatabaseSqlite\SqliteExtension;

$application->extend(new SqliteExtension([
    'memory' => true,
]));
```

Para persistir os dados em arquivo, informe somente `path`:

```php
$application->extend(new SqliteExtension([
    'path' => __DIR__ . '/../var/app.sqlite',
]));
```

Um caminho absoluto aponta diretamente para o arquivo. Um caminho relativo e
resolvido a partir do diretorio de trabalho atual do processo PHP. Garanta que
o diretorio pai exista e tenha permissao de escrita e mantenha o arquivo em um
diretorio de runtime da aplicacao, fora de `vendor/` e do codigo publicado.
SQLite nao usa `username` nem `password`.

Informe exatamente uma das configuracoes:

- `memory => true`: banco temporario exclusivo da conexao.
- `path`: caminho relativo ou absoluto do arquivo persistente.

`options` e opcional e tem `[]` como padrao.

## Conexoes nomeadas e transacoes

```php
$application->extend(new SqliteExtension([
    'connections' => [
        'default' => ['path' => __DIR__ . '/../var/app.sqlite'],
        'analytics' => ['path' => __DIR__ . '/../var/analytics.sqlite'],
    ],
]));
```

Ao usar `connections`, configure obrigatoriamente a chave `default`.
`PdoDatabase` e `TransactionManager` compartilham essa conexao. As demais
conexoes sao obtidas por `DatabaseConnectionFactory::connection('analytics')`
e mantem instancias e transacoes isoladas. `memory => true` cria um banco
independente por conexao PDO.

```php
use Elavora\Api\Extension\DatabasePdo\PdoDatabase;
use Elavora\Api\Framework\Contracts\TransactionManager;

$database = $application->container()->get(PdoDatabase::class);
$transactions = $application->container()->get(TransactionManager::class);

$transactions->begin();
try {
    $database->insert('users', ['name' => 'Ana']);
    $transactions->commit();
} catch (Throwable $exception) {
    $transactions->rollback();
    throw $exception;
}
```

## Principais pontos de entrada

- `Elavora\Api\Extension\DatabaseSqlite\SqliteExtension`

## Dependencias de runtime

- `ext-pdo_sqlite` `*`
- PHP `>=8.3`
- `elavora/api-database-pdo` `^1.0`
- `elavora/api-framework` `^1.0`

## Validacao no projeto consumidor

Depois de instalar o pacote, rode os testes da aplicacao consumidora. Para uma verificacao isolada do pacote, use container:

```bash
docker run --rm -v "${PWD}:/workspace" -w "/workspace/api-database-sqlite" composer:2 composer validate --strict --no-check-publish
docker run --rm -v "${PWD}:/workspace" -w "/workspace/api-database-sqlite" composer:2 composer check
```

`composer lint` usa somente PHP e funciona em Linux, macOS e Windows.

## Observacoes

- Mantenha regras de produto fora deste pacote.
- Prefira configurar extensoes no bootstrap da aplicacao.
- Instale apenas os modulos que a aplicacao realmente usa.
