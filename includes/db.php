<?php
/*
 * Database connection (PDO). Every query uses prepared statements (NFR-02).
 *
 * Usage:
 *   $rows = db()->query('SELECT ...')->fetchAll();
 *   $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
 *   $stmt->execute([$email]);
 */

function db()
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new TrackedPdo($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STATEMENT_CLASS    => ['TrackedStatement'],
            ]);
        } catch (PDOException $error) {
            // Log the real error for the developer; show the visitor a plain message.
            error_log('Database connection failed: ' . $error->getMessage());
            http_response_code(503);
            exit('<h1>The site is being set up</h1><p>The database is not available. '
                . 'If you are setting up the site, check the database settings in config/config.php '
                . 'and import database/schema.sql (see README).</p>');
        }
    }

    return $pdo;
}

/* ---------- Live updates (FR-LIVE-01) ----------
   Every INSERT, UPDATE or DELETE that really changes rows notes the table it changed.
   When the page has finished, each changed table's number in live_versions goes up by one.
   Open pages ask live.php for these numbers and update themselves when one of theirs moves. */

class TrackedPdo extends PDO
{
    public function exec(string $statement): int|false
    {
        $rows = parent::exec($statement);
        if ($rows) {
            note_changed_table($statement);
        }
        return $rows;
    }
}

class TrackedStatement extends PDOStatement
{
    protected function __construct()
    {
    }

    public function execute(?array $params = null): bool
    {
        $done = parent::execute($params);
        if ($done && $this->rowCount() > 0) {
            note_changed_table($this->queryString);
        }
        return $done;
    }
}

// Remembers which table an INSERT/UPDATE/DELETE changed; saved once, when the page ends.
function note_changed_table($sql)
{
    static $changed = null;
    if (!preg_match('/^\s*(?:INSERT\s+(?:IGNORE\s+)?INTO|REPLACE\s+INTO|UPDATE|DELETE\s+FROM)\s+`?(\w+)/i', $sql, $match)
        || $match[1] === 'live_versions') {
        return;
    }
    if ($changed === null) {
        $changed = [];
        register_shutdown_function(function () use (&$changed) {
            save_live_versions(array_keys($changed));
        });
    }
    $changed[$match[1]] = true;
}

function save_live_versions(array $tables)
{
    $pdo = db();
    if (!$tables || $pdo->inTransaction()) {
        return; // a transaction still open at the end was never saved, so nothing changed
    }
    try {
        $values = implode(', ', array_fill(0, count($tables), '(?, 1)'));
        $pdo->prepare("INSERT INTO live_versions (table_name, version) VALUES $values ON DUPLICATE KEY UPDATE version = version + 1")
            ->execute($tables);
    } catch (PDOException $error) {
        error_log('Live updates: ' . $error->getMessage()); // e.g. live_versions missing: pages just don't update by themselves
    }
}

// Version numbers of some tables (or all): ['orders' => 12, ...]. Tables never changed are left out.
function live_versions(array $tables = [])
{
    try {
        $sql = 'SELECT table_name, version FROM live_versions';
        if ($tables) {
            $sql .= ' WHERE table_name IN (' . implode(',', array_fill(0, count($tables), '?')) . ')';
        }
        $stmt = db()->prepare($sql);
        $stmt->execute($tables);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));
    } catch (PDOException $error) {
        return null;
    }
}

// All photos for one place on the site, in order (site_photos table, FR-WEB-10).
function site_photos($location)
{
    $stmt = db()->prepare('SELECT file, alt_text FROM site_photos WHERE location = ? ORDER BY sort_order, id');
    $stmt->execute([$location]);
    return $stmt->fetchAll();
}

// The first photo for a place on the site, or a placeholder entry if none is set.
function site_photo($location, $default_alt)
{
    $photos = site_photos($location);
    return $photos[0] ?? ['file' => '', 'alt_text' => $default_alt];
}
