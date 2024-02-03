<?php

class User {

    // GENERAL

    public static function user_info($d) {
        // vars
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $phone = isset($d['phone']) ? preg_replace('~\D+~', '', $d['phone']) : 0;
        // where
        if ($user_id) $where = "user_id='".$user_id."'";
        else if ($phone) $where = "phone='".$phone."'";
        else return [];
        // info
        $q = DB::query("SELECT user_id, plot_id, access, first_name, last_name, phone, email FROM users WHERE ".$where." LIMIT 1;") or die (DB::error());
        if ($row = DB::fetch_row($q)) {
            return [
                'id' => (int) $row['user_id'],
                'access' => (int) $row['access'],
                'plot_id' => $row['plot_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'phone' => $row['phone'],
                'email' => $row['email'],
            ];
        } else {
            return [
                'id' => 0,
                'access' => 0
            ];
        }
    }

    public static function users_list_plots($number) {
        // vars
        $items = [];
        // info
        $q = DB::query("SELECT user_id, plot_id, first_name, email, phone
            FROM users WHERE plot_id LIKE '%".$number."%' ORDER BY first_name ASC, last_name ASC;") or die (DB::error());
        while ($row = DB::fetch_row($q)) {
            $plot_ids = explode(',', $row['plot_id']);
            $val = false;
            foreach($plot_ids as $plot_id) if ($plot_id == $number) $val = true;
            if ($val) $items[] = [
                'id' => (int) $row['user_id'],
                'first_name' => $row['first_name'],
                'email' => $row['email'],
                'phone_str' => phone_formatting($row['phone'])
            ];
        }
        // output
        return $items;
    }

    public static function users_list($d = []) {
        $search = isset($d['search']) && trim($d['search']) ? $d['search'] : '';
        $offset = isset($d['offset']) && is_numeric($d['offset']) ? $d['offset'] : 0;
        $limit = 20;
        $items = [];

        $where = [];
        if ($search) 
            $where = [
                        "concat(first_name, last_name) LIKE '%".$search."%'",
                        "phone LIKE '%".$search."%'",
                        "email LIKE '%".$search."%'"
                    ];

        $where = $where ? "WHERE ".implode(" OR ", $where) : "";
        $q = DB::query("SELECT user_id, plot_id, first_name, last_name, phone, email, last_login 
            FROM users ".$where." LIMIT ".$offset.", ".$limit.";") or die (DB::error());
        while ($row = DB::fetch_row($q)) {
            $items[] = [
                'id' => (int) $row['user_id'],
                'plot_id' => $row['plot_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'phone' => $row['phone'],
                'email' => $row['email'],
                'last_login' => $row['last_login']
            ];
        }

        $q = DB::query("SELECT count(*) FROM users ".$where.";");
        $count = ($row = DB::fetch_row($q)) ? $row['count(*)'] : 0;
        $url = 'users?';
        if ($search) $url .= '&search='.$search;
        paginator($count, $offset, $limit, $url, $paginator);

        return ['items' => $items, 'paginator' => $paginator];
    }

    public static function user_edit_update($d = []) {
        if(
            empty($d['plot_id']) || preg_match("/[^0-9\,]/",$d['plot_id']) ||
            empty($d['first_name']) || preg_match("/[^a-zа-яA-ZА-Я0-9_]/",$d['first_name']) || 
            empty($d['last_name']) || preg_match("/[^a-zа-яA-ZА-Я0-9_]/",$d['last_name']) || 
            empty($d['phone']) || preg_match("/[^0-9]/",$d['phone']) ||
            empty($d['email']) || !filter_var($d['email'], FILTER_VALIDATE_EMAIL)
        ){
            die('Some data is missing');
        }

        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $email = strtolower($d['email']);

        if ($user_id) {
            $set = [];
            $set[] = "plot_id='".$d['plot_id']."'";
            $set[] = "first_name='".$d['first_name']."'";
            $set[] = "last_name='".$d['last_name']."'";
            $set[] = "email='".$email."'";
            $set[] = "phone='".$d['phone']."'";
            $set[] = "updated='".Session::$ts."'";
            $set = implode(", ", $set);
            DB::query("UPDATE users SET ".$set." WHERE user_id='".$user_id."' LIMIT 1;") or die (DB::error());
        } else {
            DB::query("INSERT INTO users (
                village_id,
                plot_id,
                first_name,
                last_name,
                email,
                phone,
                phone_code,
                phone_attempts_code,
                phone_attempts_sms,
                updated,
                last_login
            ) VALUES (
                '1',
                '".$d['plot_id']."',
                '".$d['first_name']."',
                '".$d['last_name']."',
                '".$email."',
                '".$d['phone']."',
                '1111',
                '0',
                '0',
                '".Session::$ts."',
                '0'
            );") or die (DB::error());
        }

        return User::users_fetch(['offset' => $offset]);
    }

    public static function users_fetch($d = []) {
        $users = User::users_list($d);
        HTML::assign('users', $users['items']);
        return ['html' => HTML::fetch('./partials/users_table.html'), 'paginator' => $users['paginator']];
    }

    public static function user_edit_window($d = []) {
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        HTML::assign('user', User::user_info(['user_id' => $user_id]));
        return ['html' => HTML::fetch('./partials/user_edit.html')];
    }

    public static function user_edit_delete($d = []) {
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;

        DB::query("delete from users where user_id='".$user_id."'") or die (DB::error());

        return User::users_fetch(['offset' => $offset]);
    }
}
