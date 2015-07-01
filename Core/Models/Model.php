<?php

namespace Core\Models;

use Core\Components\DbProvider;

/**
 * Model principal (dont tous les autres héritent)
 * Contient les fonctions communes à tous les models.
 */
class Model
{

    /*
        VAIRABLES
     */

    /**
     * Nom du model
     * @var string
     */
    public $name = false;
    /**
     * Le nom de la table liée au model [le nom du model en minuscules et au pluriel]
     * @var string
     */
    public $table = false;

    /**
     * clé primaire
     * @var string [id]
     */
    public $primaryKey = "id";

    /**
     * Clé étrangère
     * @var string
     */
    public $foreignKey;

    /**
     * L'instance PDO de la bdd
     * @var Object PDO
     */
    protected $bdd;

    /**
     * L'entité correspondant au model
     * @var null
     */
    public $Entity = null;

    /*
        METHODES
     */

    public function __construct($data)
    {

        $this->data = $data;

        // Initialisation des variables
        $this->setNameTableAndModel();

        // On instancie l'entity si besoin
        if ($_ENV['DB_OPTION_FETCH_MODE'] == 'PDO::FETCH_CLASS') {
            $entityName = '\App\Models\Entities\\' . $this->name . 'Entity';
            if (class_exists($entityName) && !is_null($this->Entity)) {
                $this->Entity = new $entityName();
            }
        }

        if (is_null($this->bdd)) {
            $this->bdd = DbProvider::getDb();
        }

    }

    /**
     * Permet de définir le nom de la table automatiquement
     * si ils ne sont pas déjà définis
     */
    private function setNameTableAndModel()
    {
        $classname = get_class($this);

        if (preg_match('@\\\\([\w]+)$@', $classname, $matches)) {
            if (!$this->name) {
                $this->name = $matches[1];
            }
            if (!$this->table) {
                $this->table = strtolower($matches[1]) . 's';
            }
        }
    }

    /**
     * Fonction qui permet de selectionner des données en base de donnée.
     * @param  array $conditions les conditions que l'on veut
     * @param  string $table le nom de la table
     * @internal param array|null $joins Si l'on veut des joins
     * @return array un object contenant les données demandées
     */
    public function get(array $conditions = null, $table = null)
    {

        if (method_exists($this, "beforeFilter")) {
            $this->beforeFilter($this->data);
        }

        $query = "SELECT ";

        // Si on a des champs définis
        if (!isset($conditions['fields'])) {
            $query .= "*";
        } else {
            $query .= $conditions['fields'];
        }

        if (is_null($table)) {
            $query .= " FROM " . $this->table;
        } else {
            $query .= " FROM " . $table;
        }

        // Si on doit faire un join
        if (isset($conditions['joins'])) {
            $joins = [];
            foreach ($conditions['joins'] as $j) {
                if (!isset($this->joins) || !isset($this->joins[$j])) {
                    debug("Le model " . $this->name . " n'a pas d'association avec la table $j ! Veuillez créer un tableau public \$joins dans votre model " . $this->name, false);
                } else {
                    $joins[] = " JOIN $j ON $j.{$this->primaryKey} = {$this->table}." . $this->joins[$j];
                }
            }
            $query .= implode(" AND ", $joins);
        }

        // Si on a un Where
        if (isset($conditions['where'])) {
            if (!is_array($conditions['where'])) {
                $query .= " WHERE " . $conditions['where'];
            } else {
                $query .= " WHERE ";
                $cond = [];
                foreach ($conditions['where'] as $k => $v) {
                    if (!is_numeric($v)) {
                        $v = "'" . addslashes($v) . "'";
                    }
                    $cond[] = "$k=$v";
                }
                $query .= implode(' AND ', $cond);
            }
        }

        // Si on a une limite
        if (isset($conditions['limit'])) {
            if (isset($conditions['offset'])) {
                $query .= " LIMIT " . $conditions['offset'] . "," . $conditions['limit'];
            } else {
                $query .= " LIMIT " . $conditions['limit'];
            }
        }

        // Si on a un group by
        if (isset($conditions['groupBy'])) {
            $query .= " GROUP BY " . $conditions['groupBy'];
        }

        // Si on a un order
        if (isset($conditions['order'])) {
            $query .= " ORDER BY " . $conditions['order'];
        }

        // debug($query);
        $req = $this->bdd->query($query);

        if ($_ENV['DB_OPTION_FETCH_MODE'] == 'PDO::FETCH_CLASS') {
            return $req->fetchAll(\PDO::FETCH_CLASS, 'App\\Models\\Entities\\' . $this->name . 'Entity');
        } else {
            return $req->fetchAll();
        }
    }

    /**
     * Permet de récupérer le premier enregistrement d'une table.
     * @param  array $conditions les conditions que l'on veut
     * @param  array|null $joins Si l'on veut des joins
     * @param  string $table le nom de la table
     * @return mixed un object contenant les données demandées
     */
    public function getFirst(array $conditions = null, array $joins = null, $table = null)
    {
        $result = $this->get($conditions, $joins, $table);
        return current($result);
    }

    /**
     * Permet de récupérer le dernier enregistrement d'une table.
     * @param  array $conditions les conditions que l'on veut
     * @param  array|null $joins Si l'on veut des joins
     * @param  string $table le nom de la table
     * @return mixed un object contenant les données demandées
     */
    public function getLast(array $conditions = null, array $joins = null, $table = null)
    {
        $result = $this->get($conditions, $joins, $table);
        return @end($result);
    }

    /**
     * Créer une entrée en bdd
     * @param $data
     * @param $table
     * @return bool
     */
    public function create(\stdClass $data, $table = null)
    {

        if (method_exists($this, "beforeSave")) {
            $this->beforeSave($this->data);
        }

        $fields = $values = $tmp = [];

        foreach ($data as $k => $v) {
            $fields[] = $k;
            $tmp[] = ':' . $k;
            $values[':' . $k] = $v;
        }

        $fields = "(" . implode(',', $fields) . ")";
        $tmp = "(" . implode(',', $tmp) . ")";

        if ($table == null) {
            $table = $this->table;
        }

        $sql = 'INSERT INTO ' . $table . ' ' . $fields . ' VALUES ' . $tmp;

        $pdost = $this->bdd->prepare($sql);
        try {
            $pdost->execute($values);
            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Met à jour les données
     * @param int $id l'id de l'entrée que l'on veut update
     * @param array|\stdClass $data les données
     * @param string $table le nom de la table si besoin
     * @return bool
     */
    public function update($id, \stdClass $data, $table = null)
    {
        if (method_exists($this, "beforeSave")) {
            $this->beforeSave($this->data);
        }

        $values = $tmp = [];

        foreach ($data as $d => $v) {
            $values[':' . $d] = $v;
            $tmp[] = $d . "=:" . $d;
        }

        $tmp = implode(',', $tmp);

        if ($table == null) {
            $table = $this->table;
        }

        $sql = 'UPDATE ' . $table . ' SET ' . $tmp . ' WHERE id = ' . $id;
        $pdost = $this->bdd->prepare($sql);
        try {
            $pdost->execute($values);
            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Supprime une antrée
     * @param int $id l'id de l'entrée à supprimer
     * @param string $table la table si besoin
     */
    public function delete($id, $table = null)
    {
        if (method_exists($this, "beforeDelete")) {
            $this->beforeDelete($this->data);
        }

        if ($table == null) {
            $table = $this->table;
        }
        $this->bdd->query("DELETE FROM " . $table . " WHERE id=$id");

        if (method_exists($this, "afterDelete")) {
            $this->afterDelete($this->data);
        }
    }

    /**
     * Permet de faire une pagination
     * @param array|null $joins
     * @param null $table
     * @return mixed
     */
    public function count(array $joins = null, $table = null)
    {
        $count = $this->getFirst(['fields' => "COUNT({$this->primaryKey}) as count"], $joins, $table);
        return $count->count;
    }

    /**
     * Permet de récuperer les infos en bdd pour vérifier si l'utilisateur à bien entré un bon login/mdp
     * @param string $login Le login entré par l'utilisateur
     * @return stdClass un objet content les indos trouvées.
     */
    public function getLogged($login)
    {
        $req = $this->bdd->query("SELECT id,password,role FROM users WHERE login='$login';");
        if ($_ENV['DB_OPTION_FETCH_MODE'] == 'PDO::FETCH_CLASS') {
            $req->setFetchMode(\PDO::FETCH_CLASS, 'App\\Models\\Entities\\' . $this->name . 'Entity');
        }
        return $req->fetch();
    }

    /**
     * Permet de faire un select avec une pagination
     * @param array $conditions
     * @param $nbPerPage
     * @return mixed
     */
    public function paginate(array $conditions = null, $nbPerPage)
    {
        if (!isset($_GET['page']) || $_GET['page'] < 1 || !is_numeric($_GET['page'])) {
            $_GET['page'] = 1;
        }

        $total = $this->count();
        $d['nbPages'] = ceil($total / $nbPerPage);

        if ($_GET['page'] > $d['nbPages'] && $d['nbPages'] != 0) {
            $_GET['page'] = $d['nbPages'];
        }

        $conditions['offset'] = $nbPerPage * ($_GET['page'] - 1);
        $conditions['limit'] = $nbPerPage;

        $d['results'] = $this->get($conditions);
        return $d;
    }

}