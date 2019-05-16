<?php

/**
 * Class Anonymization
 */
class Anonymization
{
    /**
     * @var \Faker\Generator
     */
    private $faker;

    /**
     * @var PDO
     */
    private $pdo;

    /**
     * @var array
     */
    private $tables;

    /**
     * @var string
     */
    public $message;

    /**
     * Anonymization constructor.
     * @param array $settings
     */
    public function __construct($settings)
    {
        $this->message = PHP_EOL . 'Heure de début du script : ' . date('Y-m-d H:i:s') . PHP_EOL;

        require_once 'vendor/fzaninotto/faker/src/autoload.php';
        $this->faker = Faker\Factory::create('fr_FR');

        $this->checkBaseSettings($settings);
        $this->checkModificationSettings($settings);

        $this->executeAnonymization();
    }

    /**
     * Anonymization destructor.
     */
    public function __destruct()
    {
        $this->message .= 'Heure de fin du script : ' . date('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;

        echo $this->message;
    }

    /**
     * Executes anonymization of the base.
     */
    private function executeAnonymization()
    {
        $executionMessage = '';

        foreach ($this->tables as $tableName => $tableSettings) {

            if ($tableSettings === null) {

                $emptyTable = $this->pdo->query("TRUNCATE {$tableName}");

                if ($emptyTable) {

                    $executionMessage .= " - La table {$tableName} a été vidé correctement." . PHP_EOL;

                } else {

                    $executionMessage .= " - Une erreur est survenu: la table {$tableName} n'a pas été vidé correctement." . PHP_EOL;

                }

            } else {

                $idFieldName = $tableSettings['idFieldName'];
                $i = 0;

                $select = $this->pdo->query("SELECT {$tableSettings['idFieldName']} FROM {$tableName}");
                $results = $select->fetchAll();

                foreach ($results as $line) {

                    $id = $line[$idFieldName];

                    $sql = "UPDATE {$tableName} SET ";
                    $updateSettings = [];

                    foreach ($tableSettings['fields'] as $fieldName => $method) {

                        $sql .= "{$fieldName} = :{$fieldName}, ";

                        $updateSettings[":{$fieldName}"] = $this->$method();

                    }

                    $sql = trim($sql, ' ,');
                    $sql .= " WHERE {$idFieldName} = {$id}";

                    $update = $this->pdo->prepare($sql);
                    $update->execute($updateSettings);

                    if ($i % 100 === 0) {

                        echo "{$i} traitement sur la table {$tableName}." . PHP_EOL;

                    }

                    $i++;

                }

                $executionMessage .= " - La table {$tableName} a été modifié correctement, {$i} lignes traitées." . PHP_EOL;

            }
        }

        if (!empty($executionMessage)) {

            $this->message .= 'Traitements effectués:' . PHP_EOL;
            $this->message .= $executionMessage;

        } else {

            $this->message .= "Aucune traitement n'a été effectué" . PHP_EOL;

        }
    }

    /**
     * Check the connection settings to the database.
     *
     * @param array $settings
     */
    private function checkBaseSettings($settings)
    {
        $host = is_string($settings['host']) ? $settings['host'] : '';
        $base = is_string($settings['base']) ? $settings['base'] : '';
        $user = is_string($settings['user']) ? $settings['user'] : '';
        $password = is_string($settings['password']) ? $settings['password'] : '';

        try {

            $this->pdo = new PDO("mysql:host={$host};dbname={$base}", $user, $password);

        } catch (PDOException $exception) {

            $this->message .= 'Les identifiants de la base de données sont incorrects.' . PHP_EOL;
            $this->message .= 'Fin du script.' . PHP_EOL;
            exit;

        }
    }

    /**
     * Check the settings passed for editing the tables.
     *
     * @param array $settings
     */
    private function checkModificationSettings($settings)
    {
        $errorMessage = '';

        if (is_array($settings['tables'])) {

            foreach ($settings['tables'] as $tableName => $tableSettings) {

                $validTableName = $this->validateMysqlName($tableName);

                if ($validTableName && is_string($tableSettings['idFieldName']) && is_array($tableSettings['fields'])) {

                    if ($this->validateMysqlName($tableSettings['idFieldName'])) {

                        $this->tables[$tableName] = ['idFieldName' => $tableSettings['idFieldName'], 'fields' => []];

                        foreach ($tableSettings['fields'] as $fieldName => $method) {

                            if ($this->validateMysqlName($fieldName)) {

                                if (method_exists($this, $method)) {

                                    $this->tables[$tableName]['fields'][$fieldName] = $method;

                                } else {

                                    $errorMessage .= " - La méthode {$method} qui devait être utilisé sur le champ {$fieldName} n'existe pas et sera donc ignorée." . PHP_EOL;

                                }

                            } else {

                                $errorMessage .= " - Le nom de champ {$fieldName} n'est pas valide et sera donc ignorée." . PHP_EOL;

                            }

                        }

                    } else {

                        $errorMessage .= " - Les nom de champ d'id {$tableSettings['idFieldName']} n'est pas valide et la table sera donc ignorée." . PHP_EOL;

                    }

                } elseif ($validTableName && $tableSettings === null) {

                    $this->tables[$tableName] = null;

                } else {

                    $errorMessage .= " - La paramètres de modification fournis pour la table {$tableName} sont incorrect, elle sera donc ignorée." . PHP_EOL;

                }

            }

        } else {

            $errorMessage .= ' - La paramètres de modification fournis pour les tables sont incorrects.' . PHP_EOL;

        }

        if (!empty($errorMessage)) {

            $this->message .= 'Vérification des paramètres de modification:' . PHP_EOL . $errorMessage;

        }
    }

    /**
     * Validate that the string parameter is either a valid table name or a valid field name for mysql (64 characters, alphanumeric and _).
     *
     * @param string $name
     * @return false|int
     */
    private function validateMysqlName($name)
    {
        return preg_match('/[A-Za-z][A-Za-z0-9_]{0,63}/', $name);
    }

    /**
     * @return string
     */
    private function lastName()
    {
        return utf8_decode($this->faker->lastName);
    }

    /**
     * @return string
     */
    private function firstName()
    {
        return utf8_decode($this->faker->firstName);
    }

    /**
     * @return string
     */
    private function fullName()
    {
        return utf8_decode("{$this->faker->lastName} {$this->faker->firstName}");
    }

    /**
     * @return string
     */
    private function schoolName()
    {
        $types_etablissement = ['Lycée', 'Collège'];

        // Défini un type d'établissement aléatoire.
        $type_etablissement = $types_etablissement[mt_rand(0, count($types_etablissement) - 1)];

        return utf8_decode($type_etablissement) . " {$this->fullName()}";
    }

    /**
     * @return string
     */
    private function rne()
    {
        return $this->faker->bothify('#######?');
    }

    /**
     * @return string
     */
    private function streetAddress()
    {
        return utf8_decode($this->faker->streetAddress);
    }

    /**
     * @return string
     */
    private function postCode()
    {
        return $this->faker->postcode;
    }

    /**
     * @return string
     */
    private function city()
    {
        return utf8_decode($this->faker->city);
    }

    /**
     * @return string
     */
    private function fullAddress()
    {
        return utf8_decode($this->faker->address);
    }

    /**
     * @return string
     */
    private function email()
    {
        return $this->faker->email;
    }

    /**
     * @return string
     */
    private function phoneNumber()
    {
        return $this->faker->phoneNumber;
    }

    /**
     * @return string
     */
    private function iban()
    {
        return $this->faker->iban();
    }

    /**
     * @return string
     */
    private function nir()
    {
        return $this->faker->nir();
    }

    /**
     * @return string
     */
    private function emptyString()
    {
        return '';
    }
}
