# MysqlLib

## Usage
1. Initialization
```php
$db = MysqlLib::init(new MysqlCredentials(
    host: "127.0.0.1",
    user: "root",
    password: "",
    db: "database",
    port: 3306
));
```
2. Create a first query
```php
class GetEmailQuery extends MysqlQuery{

    public function execute(MysqlConnection $conn, array $params): mixed{
        $result = $conn->query('SELECT email FROM users WHERE name=? LIMIT 1', "s", $params["name"]);
        if(count($result) === 1) {
            return $result[0]["email"];
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