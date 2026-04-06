<?php

class Model
{
    public function db_query(mixed $db, string $select, string $table, ?string $where = null, string $order = 'id DESC', string $limit = ''): mysqli_result|array|bool|string
    {
        $query = mysqli_query($db, "SELECT " . $select . " FROM " . $table . " ORDER BY " . $order . " " . $limit);
        if ($where <> null) {
            $query = mysqli_query($db, "SELECT " . $select . " FROM " . $table . " WHERE " . $where . " ORDER BY " . $order . " " . $limit);
        }
        if (mysqli_error($db)) {
            return false;
        } else {
            if (mysqli_num_rows($query) == 1) {
                $rows = mysqli_fetch_assoc($query);
            } else {
                $rows = [];
                while ($row = mysqli_fetch_assoc($query)) {
                    $rows[] = $row;
                }
            }
            $result = array('query' => $query, 'rows' => $rows, 'count' => mysqli_num_rows($query));
            return $result;
        }
    }
    public function db_query_join(mixed $db, string $select, string $table, string $join, ?string $where = null, string $order = 'id DESC', string $limit = ''): mysqli_result|array|bool|string
    {
        $query = mysqli_query($db, "SELECT " . $select . " FROM " . $table . " " . $join . " ORDER BY " . $order . " " . $limit);
        if ($where <> null) {
            $query = mysqli_query($db, "SELECT " . $select . " FROM " . $table . " " . $join . " WHERE " . $where . " ORDER BY " . $order . " " . $limit);
        }
        if (mysqli_error($db)) {
            return false;
        } else {
            if (mysqli_num_rows($query) == 1) {
                $rows = mysqli_fetch_assoc($query);
            } else {
                $rows = [];
                while ($row = mysqli_fetch_assoc($query)) {
                    $rows[] = $row;
                }
            }
            $result = array('query' => $query, 'rows' => $rows, 'count' => mysqli_num_rows($query));
            return $result;
        }
    }
    public function db_insert(mixed $db, string $table, array $data): string|int|bool
{
    if (!is_array($data)) {
        return false;
    }

    $fields = [];
    $values = [];

    foreach ($data as $key => $val) {
        $fields[] = "`$key`";

        if ($val === NULL) {
            $values[] = "NULL";
        } else {
            $values[] = "'" . mysqli_real_escape_string($db, (string)$val) . "'";
        }
    }

    $sql = "INSERT INTO `$table` (" . implode(', ', $fields) . ")
            VALUES (" . implode(', ', $values) . ")";

    mysqli_query($db, $sql);

    if (mysqli_error($db)) {
        throw new \Exception($db->error);
    }

    return mysqli_insert_id($db);
}

    public function db_update(mixed $db, string $table, array $data, string $where): bool
    {
        if (!is_array($data)) {
            return false;
        } else {
            $update = "";
            $count = count($data);
            $i = 1;
            foreach ($data as $k => $v) {
                if ($i == $count) {
                    $update .= $k . " = '" . $v . "'";
                } else {
                    $update .= $k . " = '" . $v . "', ";
                }
                $i++;
            }
            mysqli_query($db, "UPDATE $table SET $update WHERE $where");
            if (mysqli_error($db)) {
                return false;
            } else {
                return true;
            }
        }
    }
    public function db_delete(mixed $db, string $table, string $where): bool
    {
        $query = mysqli_query($db, "DELETE FROM $table WHERE $where");
        if ($query) {
            return true;
        } else {
            return false;
        }
    }
}