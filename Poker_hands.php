<?php

include_once __DIR__.'/vendor/autoload.php';
use PHPUnit\Framework\TestCase;

$RANK = '23456789TJQKA';
$RANKED_FUNCTIONS = [
    'highest_value_card',
    'one_pair',
    'two_pairs',
    'three_of_a_kind',
    'straight',
    'pocker_flush',
    'full_house',
    'four_of_a_kind',
    'straight_flush',
    'royal_flush'
];

/* Process file */
function read_file($filename) {
    $handle = fopen($filename, "r");
    if ($handle) {
        $won = 0;
        $total = 0;
        while (($line = fgets($handle)) !== false) {
            $total++;
            if (compare_line($line) === 1) $won++;
        }
        fclose($handle);
        echo sprintf("Player 1 wins %d times of %d", $won, $total) . PHP_EOL;
    } else {
        echo "Error opening file " . $filename  . PHP_EOL;
        return -1;
    }
    return 0;
}

/* Process one line */
function compare_line($line): int {
    $hands = array_chunk(explode(' ', $line), 5);

    return compare_hands($hands[0], $hands[1]);
}

/* Compares two hands */
function compare_hands(array $hand1, array $hand2): int {
    // get hands ranking
    $hand1rank = poker_rank($hand1);
    $hand2rank = poker_rank($hand2);

    // at first, check ranking
    if ($hand1rank['rank'] > $hand2rank['rank']) return 1;
    if ($hand1rank['rank'] < $hand2rank['rank']) return -1;

    // if ranking is the same, compare ranking value
    $by_value = by_value($hand1rank['result']['value'][0], $hand2rank['result']['value'][0]);
    if ($by_value !== 0) return $by_value;

    // if value consists of two values, compare the second
    if (strlen($hand1rank['result']['value']) === 2) {
        $by_value = by_value($hand1rank['result']['value'][1], $hand2rank['result']['value'][1]);
        if ($by_value !== 0) return $by_value;
    }

    // compare remaining cards
    $remaining1 = $hand1rank['result']['remaining'];
    $remaining2 = $hand2rank['result']['remaining'];
    while (count($remaining1) > 0) {
        $rem1 = highest_value_card($remaining1);
        $rem2 = highest_value_card($remaining2);
        $by_value = by_value($rem1['value'], $rem2['value']);

        if ($by_value !== 0) return $by_value;

        $remaining1 = $rem1['remaining'];
        $remaining2 = $rem2['remaining'];
    }

    // tie
    return 0;
}

/* Compares values according to value rank */
function by_value($val1, $val2) {
    GLOBAL $RANK;

    return strpos($RANK, $val1) <=> strpos($RANK, $val2);
}

/* Returns hand card values */
function cards_values(array $hand):array {
    return array_map(function ($el) {return strval($el[0]);}, $hand);
}

/* Returns hand card suits */
function cards_suits(array $hand):array {
    return array_map(function ($el) {return strval($el[1]);}, $hand);
}

/* Returns array without given values */
function remove_values(array $hand, string $val):array {
    return  array_values(array_filter($hand, function ($el) use ($val) {return $el[0] !== $val;}));
}

/* Returns highest value of given cards */
function highest_value_card(array $hand): array {
    $values = cards_values($hand);

    usort($values, "by_value");

    $reversed = strrev(implode($values));

    return ['value' => $reversed[0], 'remaining' => remove_values($hand, $reversed[0])];
}

/* Returns highest of pairs in hand or false */
function one_pair(array $hand) {
    $values = cards_values($hand);

    $dups = [];
    foreach(array_count_values($values) as $val => $c)
        if($c === 2) $dups[] = strval($val);

    if (count($dups) === 0) return false;

    usort($dups, "by_value");
    $val = $dups[count($dups) - 1];

    return ['value' => $val, 'remaining' => remove_values($hand, $val)];
}

/* Returns two pairs in hand or null */
function two_pairs(array $hand) {
    if (count($hand) < 4) return false;

    $values = cards_values($hand);

    $dups = [];
    foreach(array_count_values($values) as $val => $c)
        if($c === 2) $dups[] = strval($val);

    if (count($dups) != 2) return false;

    usort($dups, "by_value");
    $val = strrev(implode($dups));

    return ['value' => $val, 'remaining' => remove_values(remove_values($hand, $val[0]), $val[1])];
}

/* Returns Three cards of the same value or null */
function three_of_a_kind(array $hand) {
    $values = cards_values($hand);

    $threes = false;
    foreach(array_count_values($values) as $val => $c)
        if($c === 3) $threes = strval($val);

    if (!$threes) return false;

    return ['value' => $threes, 'remaining' => remove_values($hand, $threes)];
}

/* Returns cards of consecutive values or false */
function straight(array $hand) {
    GLOBAL $RANK;

    if (count($hand) < 5) return false;

    $values = cards_values($hand);

    usort($values, "by_value");
    $row = implode($values);
    $pos = strpos($RANK, $row);
    if ($pos === false) return false;

    return ['value' => $RANK[$pos], 'remaining' => []];
}

/* Returns suit if all cards of the same suit or false */
function pocker_flush(array $hand) {

    if (count($hand) < 5) return false;

    $suits = cards_suits($hand);

    $same = array_count_values($suits);

    if (count($same) > 1) return false;

    return ['value' => highest_value_card($hand)['value'], 'remaining' => []];
}

/* Returns two pairs in hand or null */
function full_house(array $hand) {
    if (count($hand) < 5) return false;

    $suits = cards_values($hand);

    $same = array_count_values($suits);
    if (count($same) != 2) return false;
    $two_three = array_flip($same);
    return ['value' => $two_three[3] . $two_three[2], 'remaining' => []];
}


/* Returns four cards of the same value or null */
function four_of_a_kind(array $hand) {
    $values = cards_values($hand);

    $four = false;

    foreach(array_count_values($values) as $val => $c)
        if($c === 4) $four = strval($val);

    if ($four === false) return false;

    return ['value' => $four, 'remaining' => remove_values($hand, $four)];
}

/* Returns cards of consecutive values of the same suit or false */
function straight_flush(array $hand) {
    if (!pocker_flush($hand)) return false;

    return straight($hand);
}

/* Returns cards of consecutive values of the same suit or false */
function royal_flush(array $hand) {
    if (!pocker_flush($hand)) return false;
    $straight = straight($hand);
    if (!$straight) return false;
    if ($straight['value'] !== 'T') return false;

    return ['value' => true, 'remaining' => []];
}

/* Find highest rank */
function poker_rank(array $hand) {
    GLOBAL $RANKED_FUNCTIONS;
    $ranked_function_index = count($RANKED_FUNCTIONS) - 1;
    $func = $RANKED_FUNCTIONS[$ranked_function_index];
    $value = $func($hand);

    while ($ranked_function_index > 0 && $value === false) {
        $ranked_function_index--;
        $func = $RANKED_FUNCTIONS[$ranked_function_index];
        $value =  $func($hand);
    }

    return ['rank' => $ranked_function_index, 'result' => $value];
}

/* Debug helpers */
function print_pocker($hand) {
    GLOBAL $RANKED_FUNCTIONS;
    $val = poker_rank($hand);
    return implode($hand, ' ') .
        '(' .
        $RANKED_FUNCTIONS[$val['rank']] .
        '=' .
        $val['result']['value'] .
        ')';
}

/* Tests */
class PokerHandsTestCases extends TestCase
{
    public function testHelpers() {
        $this->assertSame(by_value("T", "9"), 1, 'T should higher than 9.');
        $this->assertSame(by_value("2", "3"), -1, '2 should be lower than 3.');

        $this->assertSame(
            highest_value_card(["2H", "2C", "2S", "2D", "4D"]),
            ['value' => '4', 'remaining' => ["2H", "2C", "2S", "2D"]],
            'High Card should be 4.'
        );
        $this->assertSame(
            highest_value_card(["2H", "AC", "QS", "TS", "4D"]),
            ['value' => 'A', 'remaining' => ["2H", "QS", "TS", "4D"]],
            'High Card should be A.');
        $this->assertSame(
            one_pair(["AH", "AC", "3S", "3H", "4D"]),
            ['value' => 'A', 'remaining' => ["3S", "3H", "4D"]],
            'One pair should be A.');
        $this->assertSame(
            one_pair(["TH", "TC", "3S", "3H", "3D"]),
            ['value' => 'T', 'remaining' => ["3S", "3H", "3D"]],
            'One pair should be T.');
        $this->assertSame(
            two_pairs(["AH", "AC", "KS", "KH", "4D"]),
            ['value' => 'AK', 'remaining' => ["4D"]],
            'Pairs should be K and A.');
        $this->assertSame(
            three_of_a_kind(["AH", "AC", "KS", "KH", "KD"]),
            ['value' => 'K', 'remaining' => ["AH", "AC"]],
            'Three of a Kind should be K ');
        $this->assertSame(
            straight(["AH", "TC", "QS", "KH", "JD"]),
            ['value' => 'T', 'remaining' => []],
            'Straight should be TJQKA.');
        $this->assertSame(
            straight(["3H", "6C", "2S", "5H", "4D"]),
            ['value' => '2', 'remaining' => []],
            'Straight should be 23456.');
        $this->assertSame(
            pocker_flush(["3H", "6H", "2H", "5H", "4H"]),
            ['value' => '6', 'remaining' => []],
            'Flush should be 2.');
        $this->assertSame(
            full_house(["3S", "6H", "6D", "3H", "3D"]),
            ['value' => '36', 'remaining' => []],
            'Full house wrong.');
        $this->assertSame(
            four_of_a_kind(["3S", "3H", "6D", "3C", "3D"]),
            ['value' => '3', 'remaining' => ["6D"]],
            'Four of a Kind should be 3.');
        $this->assertSame(
            straight_flush(["3H", "6H", "2H", "5H", "4H"]),
            ['value' => '2', 'remaining' => []],
            'Straight flush should be 23456.');
        $this->assertSame(
            royal_flush(["AH", "TH", "KH", "JH", "QH"]),
            ['value' => true, 'remaining' => []],
            'Royal flush should be true.');
    }

    public function testRanking() {
        $this->assertSame(poker_rank(["2H", "7C", "QS", "TS", "4D"])['rank'], 0, 'High Card should have been');
        $this->assertSame(poker_rank(["TH", "QC", "3S", "3H", "4D"])['rank'], 1, 'One pair should have been.');
        $this->assertSame(poker_rank(["AH", "AC", "KS", "KH", "4D"])['rank'], 2, 'Two Pairs should have been.');
        $this->assertSame(poker_rank(["3H", "6C", "2S", "3H", "3D"])['rank'], 3, 'Three of a Kind should have been.');
        $this->assertSame(poker_rank(["3H", "6C", "2S", "5H", "4D"])['rank'], 4, 'Straight should have been.');
        $this->assertSame(poker_rank(["3H", "TH", "2H", "5H", "4H"])['rank'], 5, 'Flush should have been.');
        $this->assertSame(poker_rank(["3S", "6H", "6D", "3H", "3D"])['rank'], 6, 'Full house should have been.');
        $this->assertSame(poker_rank(["3S", "3H", "6D", "3C", "3D"])['rank'], 7, 'Four of a Kind should have been.');
        $this->assertSame(poker_rank(["3H", "6H", "2H", "5H", "4H"])['rank'], 8, 'Straight flush should have been.');
        $this->assertSame(poker_rank(["AH", "TH", "KH", "JH", "QH"])['rank'], 9, 'Royal flush should have been.');
    }

    public function testCases() {
        $this->assertSame(compare_line("AH TH KH JH QH 2C 2S 2S 2D 4D"), 1, 'Royal flush should win.');
        $this->assertSame(compare_line("AH TH KH JH QH AS TS KS JS QS"), 0, 'Never happen.');
        $this->assertSame(compare_line("2H 3C 4S 5S 8D 2C 3S 4S 5D 9D"), -1, 'High Card should win.');
        $this->assertSame(compare_line("5H 5C 6S 7S KD 2C 3S 8S 8D TD"), -1, 'Pair of Fives < Pair of Eights.');
        $this->assertSame(compare_line("5D 8C 9S JS AC 2C 5C 7D 8S QH"), 1, 'A > Q.');
        $this->assertSame(compare_line("2D 9C AS AH AC 3D 6D 7D TD QD"), -1, 'Three Aces < Flush with Diamonds.');
        $this->assertSame(compare_line("4D 6S 9H QH QC 3D 6D 7H QD QS"), 1, 'Pair of Queens; 9 > 7.');
        $this->assertSame(compare_line("2H 2D 4C 4D 4S 3C 3D 3S 9S 9D"), 1, 'Full House; 4 > 3.');
        $this->assertSame(compare_line("2H 2D 4C 4D 4S 4C 4D 4S 9S 9D"), -1, 'Full House; 2 < 9.');
        $this->assertSame(compare_line("2H 3H 4H 5H 6H KS AS TS QS JS"), -1, "Highest straight flush wins");
        $this->assertSame(compare_line("2H 3H 4H 5H 6H AS AD AC AH JD"), 1, "Straight flush wins of 4 of a kind");
        $this->assertSame(compare_line("AS AH 2H AD AC JS JD JC JH 3D"), 1, "Highest 4 of a kind wins");
        $this->assertSame(compare_line("2S AH 2H AS AC JS JD JC JH AD"), -1, "4 Of a kind wins of full house");
        $this->assertSame(compare_line("2S AH 2H AS AC 2H 3H 5H 6H 7H"), 1, "Full house wins of flush");
        $this->assertSame(compare_line("AS 3S 4S 8S 2S 2H 3H 5H 6H 7H"), 1, "Highest flush wins");
        $this->assertSame(compare_line("2H 3H 5H 6H 7H 2S 3H 4H 5S 6C"), 1, "Flush wins of straight");
        $this->assertSame(compare_line("2S 3H 4H 5S 6C 3D 4C 5H 6H 2S"), 0, "Equal straight is tie");
        $this->assertSame(compare_line("2S 3H 4H 5S 6C AH AC 5H 6H AS"), 1, "Straight wins of three of a kind");
        $this->assertSame(compare_line("2S 2H 4H 5S 4C AH AC 5H 6H AS"), -1, "3 Of a kind wins of two pair");
        $this->assertSame(compare_line("2S 2H 4H 5S 4C AH AC 5H 6H 7S"), 1, "2 Pair wins of pair");
        $this->assertSame(compare_line("6S AD 7H 4S AS AH AC 5H 6H 7S"), -1, "Highest pair wins");
        $this->assertSame(compare_line("2S AH 4H 5S KC AH AC 5H 6H 7S"), -1, "Pair wins of nothing");
        $this->assertSame(compare_line("2S 3H 6H 7S 9C 7H 3C TH 6H 9S"), -1, "Highest card loses");
        $this->assertSame(compare_line("4S 5H 6H TS AC 3S 5H 6H TS AC"), 1, "Highest card wins");
        $this->assertSame(compare_line("2S AH 4H 5S 6C AD 4C 5H 6H 2C"), 0, "Equal cards is tie");
    }
}

$t = new PokerHandsTestCases();
$t->testHelpers();
$t->testRanking();
$t->testCases();

if (isset($argv[1])) read_file($argv[1]); else echo "Usage: " . $argv[0] . " <filename>" . PHP_EOL;
