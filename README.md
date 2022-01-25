# MysqlLib
A Mysql library for PocketMine-MP.
- Asynchronous
- Custom Query
- Multiple Threads/Connections
## Usage
1. Initialization
```php
$threads = 2; // threads count, increase this if the query is slow and prevent blocking query
$db = MysqlLib::init(new MysqlCredentials(
    host: "127.0.0.1",
    user: "root",
    password: "",
    db: "database",
    port: 3306
), $threads);
```
2. Create a first query
```php
class GetEmailQuery extends MysqlQuery{

    public function execute(MysqlConnection $conn, array $params): mixed{
        $result = $conn->select('SELECT email FROM users WHERE name=? LIMIT 1', $params["name"]);
        $row = $result->getOneRow();
        if($row !== null) {
            return $row["email"];
        }
        return null;
    }
}
```
3. Call the query
```php
$db->query(GetEmailQuery::class, ["name" => "John_Doe"], function($email) {
    var_dump($email);
},
function() {
    var_dump("ERROR");
}
);
```
## Simple Usage
- Select (Return: rows)
```php
/** @var \VenityNetwork\MysqlLib\MysqlLib $db */
$db->rawSelect("SELECT * FROM players WHERE xuid=?", [$xuid], function(?array $rows) {
    var_dump($rows);
}, function(string $errorMessage) {
    var_dump("Error: {$errorMessage}");
});
```
- Insert (Returns: affected rows, last insert id)
```php
/** @var \VenityNetwork\MysqlLib\MysqlLib $db */
$db->rawInsert("INSERT INTO players (xuid) VALUES (?)", [$xuid], function(int $affected_rows, int $insert_id) {
    var_dump("Affected Rows: {$affected_rows}, Insert ID: {$insert_id}");
}, function(string $errorMessage) {
    // todo
});
```
- Update / Delete (Return: affected rows)
```php
/** @var \VenityNetwork\MysqlLib\MysqlLib $db */
$db->rawChange("UPDATE players SET money=? WHERE xuid=?", [$money, $xuid], function(int $affected_rows) {
    var_dump("Affected Rows: {$affected_rows}");
}, function(string $errorMessage) {
    // todo
});
```
- Generic (No result returned)
```php
/** @var \VenityNetwork\MysqlLib\MysqlLib $db */
$db->rawChange("CREATE TABLE `players` (`xuid` VARCHAR(16), `money` BIGINT) PRIMARY KEY (`xuid`)", function(bool $success) {
    // todo
}, function(string $errorMessage) {
    // todo
});
```