<?php
class AccountModel
{
    public static function getUser($account)
    {
        require_once __DIR__ . '/../../config/database.php';
        $link = create_connection();
        $sql = "SELECT * FROM register WHERE account='$account'";
        $result = execute_sql($link, "shopping", $sql);
        $user = mysqli_fetch_object($result);
        mysqli_free_result($result);
        mysqli_close($link);
        return $user;
    }
    public static function getAdmin($account)
    {
        require_once __DIR__ . '/../../config/database.php';
        $link = create_connection();
        $sql = "SELECT * FROM administrator WHERE account='$account'";
        $result = execute_sql($link, "shopping", $sql);
        $admin = mysqli_fetch_object($result);
        mysqli_free_result($result);
        mysqli_close($link);
        return $admin;
    }

    public static function accountExists($account)
    {
        require_once __DIR__ . '/../../config/database.php';
        $link = create_connection();
        $sql = "SELECT * FROM register WHERE account='$account'";
        $result = execute_sql($link, "shopping", $sql);
        $exists = mysqli_num_rows($result) > 0;
        mysqli_free_result($result);
        mysqli_close($link);
        return $exists;
    }
    public static function createAccount($name, $account, $password, $to_mail, $Birthday, $checkcode)
    {
        require_once __DIR__ . '/../../config/database.php';
        $link = create_connection();
        $sql = "INSERT INTO register (name , account , password , mail , Birthday , checkcode) VALUES ('$name','$account','$password','$to_mail','$Birthday','$checkcode')";
        $result = execute_sql($link, "shopping", $sql);
        mysqli_close($link);
        return $result;
    }
    public static function activateAccount($account)
    {
        require_once __DIR__ . '/../../config/database.php';
        $link = create_connection();
        $sql = "UPDATE register SET switch='enable' WHERE account='$account'";
        execute_sql($link, "shopping", $sql);
        mysqli_close($link);
    }
}
