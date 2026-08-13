<?php
require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dir = dirname(DB_PATH);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('CREATE TABLE IF NOT EXISTS activities (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT DEFAULT "",
            category TEXT DEFAULT "",
            date TEXT NOT NULL,
            prev_start TEXT,
            prev_end TEXT,
            created_at TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS phases (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            activity_id INTEGER NOT NULL REFERENCES activities(id) ON DELETE CASCADE,
            name TEXT NOT NULL,
            ord INTEGER NOT NULL DEFAULT 0,
            prev_start TEXT,
            prev_end TEXT,
            real_start TEXT,
            real_end TEXT
        )');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_activities_date ON activities(date)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_phases_activity ON phases(activity_id)');
    }
    return $pdo;
}
