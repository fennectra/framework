<?php

namespace Fennec\Core\Migration;

class FakeDataGenerator
{
    private const FIRST_NAMES = [
        'Alice', 'Bob', 'Charlie', 'Diana', 'Eve', 'Frank', 'Grace', 'Hugo',
        'Irene', 'Jack', 'Karen', 'Leo', 'Mona', 'Nathan', 'Olivia', 'Paul',
        'Quinn', 'Rosa', 'Sam', 'Tina', 'Ugo', 'Vera', 'William', 'Xena',
        'Yves', 'Zoe', 'Adrien', 'Brigitte', 'Camille', 'David', 'Emma',
        'Fabien', 'Gabrielle', 'Henri', 'Isabelle', 'Julien', 'Karine',
        'Laurent', 'Marie', 'Nicolas', 'Pauline', 'Romain', 'Sophie', 'Thomas',
    ];

    private const LAST_NAMES = [
        'Martin', 'Bernard', 'Dubois', 'Thomas', 'Robert', 'Richard', 'Petit',
        'Durand', 'Leroy', 'Moreau', 'Simon', 'Laurent', 'Lefebvre', 'Michel',
        'Garcia', 'David', 'Bertrand', 'Roux', 'Vincent', 'Fournier', 'Morel',
        'Girard', 'Andre', 'Mercier', 'Dupont', 'Lambert', 'Bonnet', 'Francois',
        'Martinez', 'Legrand', 'Garnier', 'Faure', 'Rousseau', 'Blanc', 'Muller',
    ];

    private const WORDS = [
        'lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur', 'adipiscing',
        'elit', 'sed', 'do', 'eiusmod', 'tempor', 'incididunt', 'ut', 'labore',
        'et', 'dolore', 'magna', 'aliqua', 'enim', 'ad', 'minim', 'veniam',
        'quis', 'nostrud', 'exercitation', 'ullamco', 'laboris', 'nisi',
        'aliquip', 'ex', 'ea', 'commodo', 'consequat', 'duis', 'aute', 'irure',
        'in', 'reprehenderit', 'voluptate', 'velit', 'esse', 'cillum', 'fugiat',
        'nulla', 'pariatur', 'excepteur', 'sint', 'occaecat', 'cupidatat',
    ];

    private const DOMAINS = [
        'example.com', 'test.org', 'demo.net', 'sample.io', 'fake.dev',
    ];

    public function name(): string
    {
        return $this->firstName() . ' ' . $this->lastName();
    }

    public function firstName(): string
    {
        return self::FIRST_NAMES[random_int(0, count(self::FIRST_NAMES) - 1)];
    }

    public function lastName(): string
    {
        return self::LAST_NAMES[random_int(0, count(self::LAST_NAMES) - 1)];
    }

    public function email(): string
    {
        $first = strtolower($this->firstName());
        $last = strtolower($this->lastName());
        $domain = self::DOMAINS[random_int(0, count(self::DOMAINS) - 1)];

        return "{$first}.{$last}." . random_int(1, 999) . "@{$domain}";
    }

    public function text(int $length = 100): string
    {
        $result = '';

        while (strlen($result) < $length) {
            $word = self::WORDS[random_int(0, count(self::WORDS) - 1)];
            $result .= ($result === '' ? ucfirst($word) : ' ' . $word);
        }

        return substr($result, 0, $length);
    }

    public function number(int $min, int $max): int
    {
        return random_int($min, $max);
    }

    public function date(string $from = '2020-01-01', string $to = '2025-12-31'): string
    {
        $start = strtotime($from);
        $end = strtotime($to);
        $timestamp = random_int($start, $end);

        return date('Y-m-d', $timestamp);
    }

    public function boolean(int $truthPercent = 50): bool
    {
        return random_int(1, 100) <= $truthPercent;
    }

    public function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function randomElement(array $items): mixed
    {
        return $items[random_int(0, count($items) - 1)];
    }

    public function phone(): string
    {
        $prefix = $this->randomElement(['+33', '+1', '+44', '+49']);
        $number = '';

        for ($i = 0; $i < 9; $i++) {
            $number .= random_int(0, 9);
        }

        return $prefix . $number;
    }
}
