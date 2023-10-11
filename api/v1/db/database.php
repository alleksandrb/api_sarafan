<?php

class DataBase
{
    private $host = 'host';
    private $dbname = 'dbname';
    private $username = "username";
    private $password = "pass";
    private $db;
    private $lo_k = 0.2;
    private $usd_to_rub = 35;

    public function __construct()
    {
        try {
            $db = new PDO("mysql:host=$this->host;dbname=$this->dbname;charset=utf8", $this->username, $this->password);
            $db->query("SET NAMES 'utf8'");
            $this->db = $db;
        } catch (PDOException $e) {
            echo "Connection failed: " . $e->getMessage();
        }
    }

    public function getUserInfo($login, $password): string
    {
        $sql = "SELECT * FROM `user` WHERE email = :email";
        $statement = ($this->db)->prepare($sql);
        $statement->execute([
            'email' => $login,
        ]);
        $user = $statement->fetch();
        if ($user) {
            if (validatePassword($password, $user["password_hash"])) {

                $info_user = [];

                $info_user['user'] = [
                    'auth_key' => $user['auth_key'],
                    'name' => $user['name'],
                    'last_name' => $user['last_name'],
                    'second_name' => $user['second_name'],
                    'phone' => $user['phone'],
                    'mentor_id' => $user['mentor_id']
                ];

                $info_user['bonus'] = $this->getUserBonus($user['id']);
                return json_encode($info_user);
            } else {
                return "password invalid";
            }
        } else {
            return "user invalid";
        }
    }

    public function getUserBonus($userId)
    {

        $bonus_finish = [
            'personal' => '',
            'group' => '',
            'personal_rub' => '',
            'group_rub' => '',
        ];

        $currentMonth = intval(date('m'));
        $currentYear = intval(date('Y'));

        $sql = "SELECT `personal`, `group`, `group_ye`, `group_BP` FROM `bonus` 
                WHERE user_id = :user_id AND month = :month AND year = :year";

        $statement = ($this->db)->prepare($sql);
        $statement->execute([
            'user_id' => $userId,
            'month' => $currentMonth,
            'year' => $currentYear,
        ]);
        $bonus = $statement->fetch(PDO::FETCH_ASSOC);

        $bonus_finish['personal'] = $bonus['personal'] ?? 0;
        $bonus_finish['group'] = $bonus['group'] ?? 0;

        $bonus_finish['personal_rub'] = $bonus['personal'] * $this->lo_k * $this->usd_to_rub; //personal

        $bonus_finish['group_rub'] = ($bonus['personal'] >= 20) ?
            ($bonus['group_ye'] + $bonus['group_BP']) * $this->usd_to_rub : 0; //group

        return $bonus_finish;
    }

    public function checkApiKey($key)
    {

        $sql = "SELECT * FROM `user` WHERE auth_key = :_key";
        $statement = ($this->db)->prepare($sql);
        $statement->execute([
            '_key' => $key,
        ]);
        $user = $statement->fetch();
        if ($user) return true;
        else return false;
    }

    public function getServiceCategory()
    {
        $categories = ($this->db)->query("SELECT `id`, `title` FROM `service_category`");
        $sub_categories = ($this->db)->query("SELECT * FROM `service`");
        $data = [];
        $sub_data = [];
        while ($sub_category = $sub_categories->fetch(PDO::FETCH_ASSOC)) {
            $sub_data[$sub_category['category_id']][] = [
                'title' => $sub_category['title'],
                'id' => $sub_category['id']
            ];
        }
        while ($category = $categories->fetch(PDO::FETCH_ASSOC)) {
            $category['service'] = $sub_data[$category['id']];
            $data[] = $category;
        }

        return $data;
    }

    public function getOrderInfo($service_id)
    {

        $sql = "SELECT salon_service.id AS `salon_service_id`, city.title AS `city_name`, 
        salon.city_id, salon.id AS `salon_id`, salon.name, 
        salon.owner_id, salon.phone, salon.email, salon.address, 
        salon.longitude, salon.latitude FROM `salon_service` 
        LEFT JOIN `salon` ON salon_service.salon_id = salon.id
        LEFT JOIN `city` ON salon.city_id = city.id WHERE salon_service.service_id = :_key;";

        $statement = ($this->db)->prepare($sql);
        $statement->execute([
            '_key' => $service_id,
        ]);

        $_salons = [];
        $salon_service_id = [];

        while ($salon = $statement->fetch(PDO::FETCH_ASSOC)) {
            $_salons[$salon['city_id']][$salon['salon_id']] = $salon;
            $salon_service_id[$salon['salon_id']][] = $salon['salon_service_id'];
        }


        foreach ($salon_service_id as $salon_id => $salon_service_ids) {
            foreach ($salon_service_ids as $id) {
                if ($id) {
                    $sql = "SELECT master_id, salon_id, name, last_name, second_name, phone,
                            email, avatar, position 
                            FROM `master_service`
                            LEFT JOIN `master` ON master_service.master_id = master.id 
                            WHERE master_service.salon_service_id = $id;";
                    $masters = ($this->db)->query($sql);

                    while ($master = $masters->fetch(PDO::FETCH_ASSOC)) {
                        $flag = 0;
                        foreach ($_salons as $city_id => $city) {
                            if ($flag == 1) break;

                            foreach ($city as $salon_id => $salon_val) {
                                if ($salon_id == $master['salon_id']) {
                                    $_salons[$city_id][$salon_id]['masters'][] = $master;
                                    $flag = 1;
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }

        $delete_salon_id = [];
        foreach ($_salons as $city_id => $salons) {
            foreach ($salons as $id => $salon) {
                if (!$salon['masters']) $delete_salon_id[] = ['salon_id' => $id, 'city_id' => $city_id];
            }
        }
        foreach ($delete_salon_id as $elem) {
            unset($_salons[$elem['city_id']][$elem['salon_id']]);
        }

        foreach ($_salons as $cityid => $salons) {
            foreach ($salons as $salonid => $salon) {
                unset($_salons[$cityid][$salonid]['salon_service_id']);
            }
        }

        return $_salons;
    }

    private function getSalonMasters($salon_id)
    {

        $sql = "SELECT * FROM `master` WHERE salon_id = :salon_id";
        $statement = ($this->db)->prepare($sql);
        $statement->execute([
            'salon_id' => $salon_id,
        ]);
        $masters = [];
        while ($master = $statement->fetch(PDO::FETCH_ASSOC)) {
            $masters[] = $master;
        }
        return $masters;
    }

    public function getScheduleForOneDay($master_id, $date)
    {

        $sql = "SELECT `start_h`, `start_m`, `end_h`, `end_m`, `recording_step` FROM `master_schedule` WHERE master_id = :master_id AND date = :date";
        $statement = ($this->db)->prepare($sql);
        $statement->execute([
            'master_id' => $master_id,
            'date' => $date
        ]);
        $schedules = $statement->fetch(PDO::FETCH_ASSOC);
        $worktime = [
            'start' => ($schedules['start_h'] * 60 + $schedules['start_m']),
            'end' => ($schedules['end_h'] * 60 + $schedules['end_m'])
        ];

        $cells = [];
        for ($i = $worktime['start']; $i <= $worktime['end']; $i += $schedules['recording_step']) {
            $cells[] = $i;
        }
        foreach ($cells as $key => $cell) {
            $cells[$key] = ['h' => floor($cell / 60), 'm' => $cell % 60];
        }

        $sql = "SELECT `start_time`, `end_time` FROM `order` WHERE master_id = :master_id AND date = :date AND status = :status";
        $statement = ($this->db)->prepare($sql);
        $statement->execute([
            'master_id' => $master_id,
            'date' => $date,
            'status' => 1
        ]);
        $orders = [];
        while ($order = $statement->fetch(PDO::FETCH_ASSOC)) {
            $orders[] = $order;
        }

        foreach ($orders as $key => $order) {
            $orders[$key]['start_time'] = explode(':', trim($order['start_time']));
            $orders[$key]['end_time'] = explode(':', trim($order['end_time']));
        }

        $arr = [];
        foreach ($cells as $key => $cell) {
            foreach ($orders as $key_order => $order) {
                if (
                    intval($cell['h']) == intval($order['start_time'][0])
                    && intval($cell['m']) == intval($order['start_time'][1])
                ) {
                    $arr[$key_order]['start'] = $key;
                }
                if ($cell['h'] == $order['end_time'][0] && $cell['m'] == $order['end_time'][1]) {
                    $arr[$key_order]['end'] = $key;
                }
            }
        }

        $delete = [];
        foreach ($arr as $index) {
            for ($i = $index['start']; $i <= $index['end']; $i++) {
                $delete[] = $i;
            }
        }

        foreach ($delete as $index) {
            unset($cells[$index]);
        }

        foreach ($cells as $key => $cell) {
            if ($cell['m'] == 0) $cell['m'] = '00';
            $cells[$key] = implode(':', $cell);
        }

        return $cells;
    }
}
