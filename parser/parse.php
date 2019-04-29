<?php
/**
 * Author: Adam Melichar
 * Login: xmelic22
 * Project: parse.php
 */


// opcode list with param count and type
$opCodeList = [
    'MOVE' => ['var', 'symb'],
    'CREATEFRAME' => [],
    'PUSHFRAME' => [],
    'POPFRAME' => [],
    'DEFVAR' => ['var'],
    'CALL' => ['label'],
    'RETURN' => [],
    'PUSHS' => ['symb'],
    'POPS' => ['var'],
    'ADD' => ['var', 'symb', 'symb'],
    'SUB' => ['var', 'symb', 'symb'],
    'MUL' => ['var', 'symb', 'symb'],
    'IDIV' => ['var', 'symb', 'symb'],
    'LT' => ['var', 'symb', 'symb'],
    'GT' => ['var', 'symb', 'symb'],
    'EQ' => ['var', 'symb', 'symb'],
    'AND' => ['var', 'symb', 'symb'],
    'OR' => ['var', 'symb', 'symb'],
    'NOT' => ['var', 'symb'],
    'INT2CHAR' => ['var', 'symb'],
    'STRI2INT' => ['var', 'symb', 'symb'],
    'READ' => ['var', 'type'],
    'WRITE' => ['symb'],
    'CONCAT' => ['var', 'symb', 'symb'],
    'STRLEN' => ['var', 'symb'],
    'GETCHAR' => ['var', 'symb', 'symb'],
    'SETCHAR' => ['var', 'symb', 'symb'],
    'TYPE' => ['var', 'symb'],
    'LABEL' => ['label'],
    'JUMP' => ['label'],
    'JUMPIFEQ' => ['label', 'symb', 'symb'],
    'JUMPIFNEQ' => ['label', 'symb', 'symb'],
    'EXIT' => ['symb'],
    'DPRINT' => ['symb'],
    'BREAK' => [],
];

parseCommandLineArg();
checkHeader();
parse($opCodeList);

/**
 * parse help argument from command line
 */
function parseCommandLineArg()
{
    $shortopts = "h";
    $longopts = array("help");

    $opts = getopt($shortopts, $longopts);

    if(isset($opts["help"]) or isset($opts["h"]))
    {
        echo("parse.php help:\n");
        echo("--help    Vypise tuhle napovedu.\n\n");
        echo("Skript načte ze standardního vstupu zdrojový kód v IPPcode19,\n");
        echo("zkontroluje lexikální a syntaktickou správnost kódu a vypíše\n");
        echo("na standardní výstup XML reprezentaci programu\n\n");
        echo("21 - chybná nebo chybějící hlavička ve zdrojovém kódu zapsaném v IPPcode19\n");
        echo("22 - neznámý nebo chybný operační kód ve zdrojovém kódu zapsaném v IPPcode19\n");
        echo("23 - jiná lexikální nebo syntaktická chyba zdrojového kódu zapsaného v IPPcode19\n");
        exit(0);
    }
}

/**
 * check header in input file
 */
function checkHeader()
{
    $line = fgets(STDIN);

    $line = preg_replace('/#.*/','',$line); // remove comments
    $line = trim($line);  // remove white spaces
    $line = strtoupper($line);

    if ($line == "IPPCODE19")
    {
        fwrite(STDERR, "ERROR, incorrect file header.\n");
        exit(21);
    }
}

/**
 * @param $opCodeList - global list with all operation code
 */
function parse($opCodeList)
{
    /** @var SimpleXMLElement */
    $program = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><program></program>');
    $program->addAttribute("language", "IPPcode19");

    while ($line = fgets(STDIN))
    {
        $line = preg_replace('/#.*/','',$line); // remove comments
        $line = trim($line); // remove white spaces
        $wordsOnLine = preg_split('/\s+/', $line, -1, PREG_SPLIT_NO_EMPTY); // split string by white spaces

        if (isset($wordsOnLine[0]))
        {
            $instr = $program->addChild("instruction");
            $wordsOnLine[0] = strtoupper($wordsOnLine[0]); // potential opcode put to upper
            instructionValidate($wordsOnLine[0], $opCodeList, $instr);

            if ((count($wordsOnLine) - 1) > count($opCodeList[$wordsOnLine[0]])) // check count of arguments
            {
                fwrite(STDERR, "ERROR, invalid count of arguments.\n");
                exit(23);
            }

            for ($i = 1; $i <= count($opCodeList[$wordsOnLine[0]]); $i++) // validate every argument of opcode
            {
                paramValidate($opCodeList[$wordsOnLine[0]][$i - 1], $wordsOnLine[$i], $i, $instr);
            }
        }
    }
    echo $program->asXML(); // print XML on STDOUT
}

/**
 * function validates instruction and add it to the XML tree
 * @param $opcode - string of potential operation code
 * @param $opCodeList - global list with all operation code
 * @param SimpleXMLElement $instr - instruction branch of XML tree
 */
function instructionValidate($opcode, $opCodeList, SimpleXMLElement $instr)
{
    static $instrIndex = 1;

    if (array_key_exists($opcode, $opCodeList))
    {
        $instr->addAttribute("order", $instrIndex);
        $instr->addAttribute("opcode", $opcode);
        $instrIndex++;
    }
    else
    {
        fwrite(STDERR, "ERROR, incorrect opcode: $opcode\n");
        exit(22);
    }
}

/**
 * function validates argument and add it to the XML tree
 * @param $paramType - expected param type
 * @param $arg - arg to validate
 * @param $index - arg index
 * @param SimpleXMLElement $instr - instruction branch of XML tree
 */
function paramValidate($paramType, $arg, $index, SimpleXMLElement $instr)
{
    if ($paramType == 'var' && isset($arg) && preg_match('/^(LF|GF|TF)@[a-zA-Z\-_$&%*!?]{1}[-_$&%*!?\w]*$/', $arg))
    { $type = "var"; }
    elseif ($paramType == 'symb' && isset($arg) && preg_match('/^(LF|GF|TF)@[a-zA-Z\-_$&%*!?]{1}[-_$&%*!?\w]*$/', $arg))
    { $type = "var"; }
    elseif ($paramType == 'symb' && isset($arg) && preg_match('/^int@(-|\+)?\d+$/', $arg))
    { $type = "int"; }
    elseif ($paramType == 'symb' && isset($arg) && preg_match('/^bool@(true|false$)/', $arg))
    { $type = "bool"; }
    elseif ($paramType == 'symb' && isset($arg) && preg_match('/^string@(([^\\\\#\s]|\\\\[\d]{3})+|)$/', $arg))
    { $type = "string"; }
    elseif ($paramType == 'symb' && isset($arg) && preg_match('/^nil@nil$/', $arg))
    { $type = "nil"; }
    elseif ($paramType == 'label' && isset($arg) && preg_match('/^[-_$&%*!?\w]+$/', $arg))
    { $type = "label"; }
    elseif ($paramType == 'type' && isset($arg) && preg_match('/^int|bool|string$/', $arg))
    { $type = "type"; }
    else
    {
        fwrite(STDERR, "ERROR, incorrect argument: $arg\n");
        exit(23);
    }
    $value = preg_replace('/^(int|bool|string|nil)@/','', $arg);
    $param = $instr->addChild("arg$index", $value);
    $param->addAttribute("type", $type);
}