<?php
//!!! По какой-то причине в документации Yandex REST API указаны не все возможные параметры запросов.
//!!! Полную информацию можно узнать на Яндекс Полигоне
//https://yandex.ru/dev/disk/poligon/

//Rest API запросы возвращают этот класс
class YandexReturn
{
    //Информация о HTTP запросе
    //https://www.php.net/manual/ru/function.curl-getinfo.php
    public $info;

    //Тип ответа от Rest API
    //В случае ошибки равен "Error"
    //Иначе зависит от самого запроса
    //https://yandex.ru/dev/disk/api/reference/response-objects.html
    public $data_type;

    //Информация полученная от запроса
    //https://yandex.ru/dev/disk/api/reference/response-objects.html
    public $data;

    public function __construct($ch, $json = true) {
        $this->data = curl_exec($ch);
        $this->info = curl_getinfo($ch);

        if ($json)  $this->data = json_decode($this->data);
    }
}

class YandexRestAPI
    {
        //Токен необходимый для большинства запросов
        //https://yandex.ru/dev/oauth/doc/dg/concepts/about.html
        public $OAuth;

        public function __construct($oauth) {
            $this->OAuth = $oauth;
        }

        private function curlRequest($url, $answer_type, $ok_code = 200, $request = "GET", $use_token = true) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request);
            if ($use_token) curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: OAuth " . $this->OAuth) );

            $answer = new YandexReturn($ch);

            if (is_array($ok_code)) {
                $answer->data_type = "Error";

                foreach ($ok_code as $code) {
                    if ($answer->info["http_code"] == $code) {
                        $answer->data_type = $answer_type;
                        break;
                    }
                }
            } else {
                if ($answer->info["http_code"] == $ok_code) {
                    $answer->data_type = $answer_type;
                } else {
                    $answer->data_type = "Error";
                }
            }

            curl_close($ch);

            return $answer;
        }

        //Базовые операции

        //Данные о Диске пользователя
        //https://yandex.ru/dev/disk/api/reference/capacity.html
        public function getDiskData($fields = "") {
            return $this->curlRequest("https://cloud-api.yandex.net/v1/disk?fields=$fields", "Disk");
        }

        //Метаинформация о файле или папке
        //https://yandex.ru/dev/disk/api/reference/meta.html
        //Уникальный параметр - trash. Указывает, находиться ли файл или папка в корзине или нет
        public function getMetaInfo($path, $trash = false, $fields = "",
        $limit = 20, $offset = 0, $preview_crop = false, $preview_size = "", $sort = "name") {

            $preview_crop = $preview_crop ? 'true' : 'false';

            $url = "https://cloud-api.yandex.net/v1/disk/";

            if ($trash) $url = $url . "trash/";

            $url = $url . "resources?".
            "path=$path".
            "&fields=$fields".
            "&limit=$limit".
            "&offset=$offset".
            "&preview_crop=$preview_crop".
            "&preview_size=$preview_size".
            "&sort=$sort";

            return $this->curlRequest($url, "Resource");
        }

        //Плоский список всех файлов
        //https://yandex.ru/dev/disk/api/reference/all-files.html
        public function getFlatList($limit = 20, $media_type = "", $offset = 0, $fields = "",
        $preview_size = "", $preview_crop = false) {

            $preview_crop = $preview_crop ? 'true' : 'false';

            $url = "https://cloud-api.yandex.net/v1/disk/resources/files?".
            "limit=$limit".
            "&media_type=$media_type".
            "&offset=$offset".
            "&fields=$fields".
            "&preview_size=$preview_size".
            "&preview_crop=$preview_crop";

            return $this->curlRequest($url, "FilesResourceList");
        }

        //Последние загруженные файлы
        //https://yandex.ru/dev/disk/api/reference/recent-upload.html
        public function lastUploaded($limit = 20, $media_type = "", $fields = "",
        $preview_size = "", $preview_crop = false) {

            $preview_crop = $preview_crop ? 'true' : 'false';

            $url = "https://cloud-api.yandex.net/v1/disk/resources/last-uploaded?".
            "limit=$limit".
            "&media_type=$media_type".
            "&fields=$fields".
            "&preview_size=$preview_size".
            "&preview_crop=$preview_crop";

            return $this->curlRequest($url, "LastUploadedResourceList");
        }

        //Добавление метаинформации для ресурса
        //https://yandex.ru/dev/disk/api/reference/meta-add.html
        //Параметр - meta, то же что и тела запроса. Только указывается не в JSON формате, а в виде объекта.
        public function setMetaInfo($path, $meta, $fields = "") {
            $data = new stdClass;
            $data->custom_properties = $meta;

            $ch = curl_init("https://cloud-api.yandex.net/v1/disk/resources?path=$path&fields=$fields");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: OAuth " . $this->OAuth,
            "Content-Type: application/json"
            ));

            $answer = new YandexReturn($ch);

            if ($answer->info["http_code"] == 200) {
                $answer->data_type = "Resource";
            } else {
                $answer->data_type = "Error";
            }

            curl_close($ch);

            return $answer;
        }

        //Скачивание и загрузка

        //Запрос URL для загрузки
        //https://yandex.ru/dev/disk/api/reference/upload.html#url-request
        public function getUploadURL($path, $overwrite = false, $fields = "") {
            $overwrite = $overwrite ? 'true' : 'false';

            return $this->curlRequest("https://cloud-api.yandex.net/v1/disk/resources/upload?".
            "path=$path&overwrite=$overwrite&fields=$fields", "Link");
        }

        //Загрузить файл по полученному адресу
        //https://yandex.ru/dev/disk/api/reference/upload.html#response-upload
        public function uploadFile($href, $fp) {
            $size = fstat($fp)["size"];

            $ch = curl_init($href);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_PUT, 1);
            curl_setopt($ch, CURLOPT_INFILE, $fp);
            curl_setopt($ch, CURLOPT_INFILESIZE, $size);

            $answer = new YandexReturn($ch, false);

            if ($answer->info["http_code"] == 201 or $answer->info["http_code"] == 202) {
                $answer->data_type = null;
            } else {
                $answer->data_type = "Error";
            }

            curl_close($ch);

            return $answer;
        }

        //Скачивание файла из интернета на Диск
        //https://yandex.ru/dev/disk/api/reference/upload-ext.html
        public function uploadFromWeb($url, $path, $fields = "", $disable_redirects = false) {
            $disable_redirects = $disable_redirects ? 'true' : 'false';

            return $this->curlRequest("https://cloud-api.yandex.net/v1/disk/resources/upload?".
            "url=$url".
            "&path=$path".
            "&fields=$fields".
            "&disable_redirects=$disable_redirects"
            , "Link", 202, "POST");
        }

        //Запросить URL для скачивания
        //https://yandex.ru/dev/disk/api/reference/content.html#url-request
        public function getDownloadUrl($path, $fields = "") {
            return $this->curlRequest("https://cloud-api.yandex.net/v1/disk/resources/download?".
            "path=$path&fields=$fields", "Link");
        }

        //Скачать файл по полученному адресу, указав тот же OAuth-токен, что и в исходном запросе
        //https://yandex.ru/dev/disk/api/reference/content.html#response-upload
        //Если в $fp = null, содержимое файла будет выводиться в окно браузера
        public function downloadFile($href, $fp = null) {
                $ch = curl_init($href);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                if ($fp) curl_setopt($ch, CURLOPT_FILE, $fp);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

                $answer = new YandexReturn($ch, false);

                if ($answer->info["http_code"] == 200) {
                    $answer->data_type = null;
                } else {
                    $answer->data_type = "Error";
                }

                curl_close($ch);
                return $answer;
        }

        //Операции над файлами и папками

        //Копирование файла или папки
        //https://yandex.ru/dev/disk/api/reference/copy.html
        public function copyItems($from, $path, $fields = "", $force_async = false, $overwrite = false) {
            $overwrite = $overwrite ? 'true' : 'false';
            $force_async = $force_async ? 'true' : 'false';

            return $this->curlRequest("https://cloud-api.yandex.net/v1/disk/resources/copy?".
            "from=$from".
            "&path=$path".
            "&fields=$fields".
            "&force_async=$force_async".
            "&overwrite=$overwrite",
            "Link", array(201, 202), "POST");
        }

        //Перемещение файла или папки
        //https://yandex.ru/dev/disk/api/reference/move.html
        public function moveItems($from, $path, $fields = "", $force_async = false, $overwrite = false) {
            $overwrite = $overwrite ? 'true' : 'false';
            $force_async = $force_async ? 'true' : 'false';

            return $this->curlRequest("https://cloud-api.yandex.net/v1/disk/resources/move?".
            "from=$from".
            "&path=$path".
            "&fields=$fields".
            "&force_async=$force_async".
            "&overwrite=$overwrite",
            "Link", array(201, 202), "POST");
        }

        //Удаление файла или папки
        //https://yandex.ru/dev/disk/api/reference/delete.html
        public function deleteItem($path, $fields = "", $force_async = false,
        $md5 = "", $permanently = false) {

            $permanently = $permanently ? 'true' : 'false';
            $force_async = $force_async ? 'true' : 'false';

            return $this->curlRequest("https://cloud-api.yandex.net/v1/disk/resources?".
            "path=$path".
            "&fields=$fields".
            "&force_async=$force_async".
            "&md5=$md5".
            "&permanently=$permanently",
            "Link", array(200, 202, 204), "DELETE");
        }

        //Создание папки
        //https://yandex.ru/dev/disk/api/reference/create-folder.html
        public function createDir($path, $fields = "") {
            return $this->curlRequest("https://cloud-api.yandex.net/v1/disk/resources?path=$path&fields=$fields",
            "Link", 201, "PUT");
        }

        //Публичные файлы и папки

        //Публикация файла или папки
        //https://yandex.ru/dev/disk/api/reference/publish.html#publish
        public function publishItem($path, $fields = "") {
            return $this->curlRequest("https://cloud-api.yandex.net/v1/disk/resources/publish?path=$path&fields=$fields",
            "Link", 200, "PUT");
        }

        //Закрытие доступа к ресурсу
        //https://yandex.ru/dev/disk/api/reference/publish.html#unpublish-q
        public function unpublishItem($path, $fields = "") {
            return $this->curlRequest("https://cloud-api.yandex.net/v1/disk/resources/unpublish?path=$path&fields=$fields",
            "Link", 200, "PUT");
        }

        //Метаинформация о публичном ресурсе
        //https://yandex.ru/dev/disk/api/reference/public.html#meta
        public function public_getMetaInfo($public_key, $path = "", $fields = "",
        $limit = 20, $offset = 0, $preview_crop = false, $preview_size = "", $sort = "name") {

            $preview_crop = $preview_crop ? 'true' : 'false';

            return $this->curlRequest("https://cloud-api.yandex.net/v1/disk/public/resources?".
            "public_key=$public_key".
            "&path=$path".
            "&fields=$fields".
            "&limit=$limit".
            "&offset=$offset".
            "&preview_crop=$preview_crop".
            "&preview_size=$preview_size".
            "&sort=$sort"
            , "Resource", 200, "GET", false);
        }

        //Запрос на скачивание публичного файла или папки
        //Скачивание файла по готовой ссылке происходит через функцию downloadFile
        //https://yandex.ru/dev/disk/api/reference/public.html#download
        public function public_getDownloadUrl($public_key, $path = "", $fields = "") {
            return $this->curlRequest("https://cloud-api.yandex.net/v1/disk/public/resources/download?".
            "public_key=$public_key&path=$path&fields=$fields", "Link", 200, "GET", false);
        }

        //Сохранение публичного файла в «Загрузки»
        //https://yandex.ru/dev/disk/api/reference/public.html#save
        public function public_saveToDisk($public_key, $fields = "", $force_async = false,
        $name = "", $path = "", $save_path = "") {

            $force_async = $force_async ? 'true' : 'false';

            return $this->curlRequest("https://cloud-api.yandex.net/v1/disk/public/resources/save-to-disk?".
            "public_key=$public_key".
            "&fields=$fields".
            "&force_async=$force_async".
            "&name=$name",
            "&path=$path".
            "&save_path=$save_path".
            "Link", array(201, 202), "POST");
        }

        //Список опубликованных ресурсов
        //https://yandex.ru/dev/disk/api/reference/recent-public.html
        public function getPublicItems($limit = 20, $offset = 0, $type = "",
        $fields = "", $preview_size = "") {

            return $this->curlRequest("https://cloud-api.yandex.net/v1/disk/resources/public?".
            "limit=$limit".
            "&offset=$offset".
            "&type=$type".
            "&fields=$fields".
            "&preview_size=$preview_size",
            "PublicResourcesList");
        }

        //Корзина

        //Очистка Корзины
        //https://yandex.ru/dev/disk/api/reference/trash-delete.html
        public function clearTrash($path = "", $fields = "", $force_async = false) {
            $force_async = $force_async ? 'true' : 'false';

            return $this->curlRequest("https://cloud-api.yandex.net/v1/disk/trash/resources?".
            "path=$path&fields=$fields&force_async=$force_async",
            "Link", array(204, 202), "DELETE");
        }

        //Восстановление файла или папки из Корзины
        //https://yandex.ru/dev/disk/api/reference/trash-restore.html
        public function restoreTrash($path, $name = "",
        $fields = "", $force_async = false, $overwrite = false) {

            $force_async = $force_async ? 'true' : 'false';
            $overwrite = $overwrite ? 'true' : 'false';

            return $this->curlRequest("https://cloud-api.yandex.net/v1/disk/trash/resources/restore?".
            "path=$path&fields=$fields&force_async=$force_async&name=$name&overwrite=$overwrite",
            "Link", array(201, 202), "PUT");
        }

        //Статус операции
        //https://yandex.ru/dev/disk/api/reference/operations.html
        public function getStatus($id) {
            return $this->curlRequest("https://cloud-api.yandex.net/v1/disk/operations/$id", "Operation");
        }
    }