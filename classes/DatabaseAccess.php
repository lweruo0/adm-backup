<?php

namespace AdmBackup;

/**
 * Zugangsdaten einer MySQL-Datenbank.
 */
final readonly class DatabaseAccess
{
    public string $host;
    public int $port;

    /**
     * @param string $host Host, darf "host:port" enthalten
     * @param int $port Port (wird durch einen Port im Host überschrieben)
     * @param string $db Datenbankname
     * @param string $user Benutzername
     * @param string $password Passwort
     */
    public function __construct(
        string $host,
        int $port,
        public string $db,
        public string $user,
        public string $password
    ) {
        if (str_contains($host, ':')) {
            [$host, $hostPort] = explode(':', $host, 2);
            $port = (int) $hostPort;
        }
        $this->host = $host;
        $this->port = $port;
    }

    /**
     * Gemeinsame Kommandozeilenargumente für mysql und mysqldump (ohne Passwort).
     *
     * @return list<string>
     */
    public function clientArguments(): array
    {
        return [
            '--host=' . $this->host,
            '--port=' . $this->port,
            '--user=' . $this->user,
            '--default-character-set=utf8mb4',
        ];
    }

    /**
     * Umgebung für einen Kindprozess; das Passwort steht nicht in der Prozessliste.
     *
     * @return array<string,string>
     */
    public function processEnvironment(): array
    {
        return ['MYSQL_PWD' => $this->password] + getenv();
    }
}
