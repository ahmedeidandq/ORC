<?php

class Database
{
    private static $basePath = '';

    public static function init()
    {
        self::$basePath = __DIR__ . '/../storage';

        $dirs = array(self::$basePath, self::$basePath . '/clones');
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    public static function clonesPath()
    {
        return self::$basePath . '/clones';
    }

    public static function saveClone(array $clone)
    {
        $path = self::clonesPath() . '/' . $clone['id'] . '.json';
        file_put_contents($path, json_encode($clone, JSON_PRETTY_PRINT));
    }

    public static function getClone($id)
    {
        $path = self::clonesPath() . '/' . $id . '.json';
        if (!file_exists($path)) return null;
        return json_decode(file_get_contents($path), true);
    }

    public static function getAllClones()
    {
        $clones = array();
        $files = glob(self::clonesPath() . '/*.json');
        if (!$files) return array();

        foreach ($files as $file) {
            $clone = json_decode(file_get_contents($file), true);
            if ($clone) {
                $clones[] = $clone;
            }
        }

        usort($clones, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        return $clones;
    }

    public static function deleteClone($id)
    {
        $path = self::clonesPath() . '/' . $id . '.json';
        if (file_exists($path)) unlink($path);
    }

    public static function nextCloneId()
    {
        $files = glob(self::clonesPath() . '/*.json');
        if (!$files) return 1;

        $max = 0;
        foreach ($files as $file) {
            $id = (int) basename($file, '.json');
            if ($id > $max) $max = $id;
        }
        return $max + 1;
    }

    public static function imagesPath()
    {
        return self::$basePath . '/images.json';
    }

    public static function getAllImageMappings()
    {
        $path = self::imagesPath();
        if (!file_exists($path)) return array();
        $data = json_decode(file_get_contents($path), true);
        return is_array($data) ? $data : array();
    }

    public static function getImageMappingsBySource($sourceContainer)
    {
        $all = self::getAllImageMappings();
        $out = array();
        foreach ($all as $m) {
            if (isset($m['source_container']) && $m['source_container'] === $sourceContainer) {
                $out[] = $m;
            }
        }
        return $out;
    }

    public static function saveImageMapping($sourceContainer, $image)
    {
        $all = self::getAllImageMappings();
        foreach ($all as $m) {
            if (isset($m['source_container']) && $m['source_container'] === $sourceContainer && $m['image'] === $image) {
                return;
            }
        }
        $all[] = array(
            'source_container' => $sourceContainer,
            'image'            => $image,
            'created_at'       => date('Y-m-d H:i:s'),
        );
        file_put_contents(self::imagesPath(), json_encode($all, JSON_PRETTY_PRINT));
    }

    public static function deleteImageMapping($sourceContainer, $image)
    {
        $all = self::getAllImageMappings();
        $out = array();
        foreach ($all as $m) {
            if (isset($m['source_container']) && $m['source_container'] === $sourceContainer && $m['image'] === $image) {
                continue;
            }
            $out[] = $m;
        }
        file_put_contents(self::imagesPath(), json_encode($out, JSON_PRETTY_PRINT));
    }
}
