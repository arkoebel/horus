<?php
/**
 * FINE granularity DIFF
 *
 * Computes a set of instructions to convert the content of
 * one string into another.
 *
 * Copyright (c) 2011 Raymond Hill (http://raymondhill.net/blog/?p=441)
 *
 * Licensed under The MIT License
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @copyright Copyright 2011 (c) Raymond Hill (http://raymondhill.net/blog/?p=441)
 * @link http://www.raymondhill.net/finediff/
 * @version 0.6
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

/**
 * Usage (simplest):
 *
 *   include 'finediff.php';
 *
 *   // for the stock stack, granularity values are:
 *   // FineDiff::$paragraphGranularity = paragraph/line level
 *   // FineDiff::$sentenceGranularity = sentence level
 *   // FineDiff::$wordGranularity = word level
 *   // FineDiff::$characterGranularity = character level [default]
 *
 *   $opcodes = FineDiff::getDiffOpcodes($fromText, $toText [, $granularityStack = null] );
 *   // store opcodes for later use...
 *
 *   ...
 *
 *   // restore $toText from $fromText + $opcodes
 *   include 'finediff.php';
 *   $toText = FineDiff::renderToTextFromOpcodes($fromText, $opcodes);
 *
 *   ...
 */

/**
 * Persisted opcodes (string) are a sequence of atomic opcode.
 * A single opcode can be one of the following:
 *   c | c{n} | d | d{n} | i:{c} | i{length}:{s}
 *   'c'        = copy one character from source
 *   'c{n}'     = copy n characters from source
 *   'd'        = skip one character from source
 *   'd{n}'     = skip n characters from source
 *   'i:{c}     = insert character 'c'
 *   'i{n}:{s}' = insert string s, which is of length n
 *
 * Do not exist as of now, under consideration:
 *   'm{n}:{o}  = move n characters from source o characters ahead.
 *   It would be essentially a shortcut for a delete->copy->insert
 *   command (swap) for when the inserted segment is exactly the same
 *   as the deleted one, and with only a copy operation in between.
 *   TODO: How often this case occurs? Is it worth it? Can only
 *   be done as a postprocessing method (->optimize()?)
 */
abstract class FineDiffOp
{
    abstract public function getFromLen();
    abstract public function getToLen();
    abstract public function getOpcode();
}

class FineDiffDeleteOp extends FineDiffOp
{
    public function __construct($len)
    {
        $this->fromLen = $len;
    }
    public function getFromLen()
    {
        return $this->fromLen;
    }
    public function getToLen()
    {
        return 0;
    }
    public function getOpcode()
    {
        if ($this->fromLen === 1) {
            return 'd';
        }
        return "d{$this->fromLen}";
    }
}

class FineDiffInsertOp extends FineDiffOp
{
    public function __construct($text)
    {
        $this->text = $text;
    }
    public function getFromLen()
    {
        return 0;
    }
    public function getToLen()
    {
        return strlen($this->text);
    }
    public function getText()
    {
        return $this->text;
    }
    public function getOpcode()
    {
        $toLen = strlen($this->text);
        if ($toLen === 1) {
            return "i:{$this->text}";
        }
        return "i{$toLen}:{$this->text}";
    }
}

class FineDiffReplaceOp extends FineDiffOp
{
    public function __construct($fromLen, $text)
    {
        $this->fromLen = $fromLen;
        $this->text = $text;
    }
    public function getFromLen()
    {
        return $this->fromLen;
    }
    public function getToLen()
    {
        return strlen($this->text);
    }
    public function getText()
    {
        return $this->text;
    }
    public function getOpcode()
    {
        if ($this->fromLen === 1) {
            $delOpcode = 'd';
        } else {
            $delOpcode = "d{$this->fromLen}";
        }
        $toLen = strlen($this->text);
        if ($toLen === 1) {
            return "{$delOpcode}i:{$this->text}";
        }
        return "{$delOpcode}i{$toLen}:{$this->text}";
    }
}

class FineDiffCopyOp extends FineDiffOp
{
    public function __construct($len)
    {
        $this->len = $len;
    }
    public function getFromLen()
    {
        return $this->len;
    }
    public function getToLen()
    {
        return $this->len;
    }
    public function getOpcode()
    {
        if ($this->len === 1) {
            return 'c';
        }
        return "c{$this->len}";
    }
    public function increase($size)
    {
        return $this->len += $size;
    }
}

/**
 * FineDiff ops
 *
 * Collection of ops
 */
class FineDiffOps
{
    public function appendOpcode($opcode, $from, $fromOffset, $fromLen)
    {
        if ($opcode === 'c') {
            $edits[] = new FineDiffCopyOp($fromLen);
        } elseif ($opcode === 'd') {
            $edits[] = new FineDiffDeleteOp($fromLen);
        } else {
			/* if ( $opcode === 'i' ) */
            $edits[] = new FineDiffInsertOp(substr($from, $fromOffset, $fromLen));
        }
    }
    public $edits = array();
}

/**
 * FineDiff class
 *
 * TODO: Document
 *
 */
class FineDiff
{

    /**------------------------------------------------------------------------
     *
     * Public section
     *
     */

    /**
     * Constructor
     * ...
     * The $granularityStack allows FineDiff to be configurable so that
     * a particular stack tailored to the specific content of a document can
     * be passed.
     */
    public function __construct($fromText = '', $toText = '', $granularityStack = null)
    {
        // setup stack for generic text documents by default
        $this->granularityStack = $granularityStack ? $granularityStack : FineDiff::$characterGranularity;
        $this->edits = array();
        $this->fromText = $fromText;
        $this->doDiff($fromText, $toText);
    }

    public function getOps()
    {
        return $this->edits;
    }

    public function getOpcodes()
    {
        $opcodes = array();
        foreach ($this->edits as $edit) {
            $opcodes[] = $edit->getOpcode();
        }
        return implode('', $opcodes);
    }

    public function renderDiffToHTML()
    {
        $inOffset = 0;
        ob_start();
        foreach ($this->edits as $edit) {
            $n = $edit->getFromLen();
            if ($edit instanceof FineDiffCopyOp) {
                FineDiff::renderDiffToHTMLFromOpcode('c', $this->fromText, $inOffset, $n);
            } elseif ($edit instanceof FineDiffDeleteOp) {
                FineDiff::renderDiffToHTMLFromOpcode('d', $this->fromText, $inOffset, $n);
            } elseif ($edit instanceof FineDiffInsertOp) {
                FineDiff::renderDiffToHTMLFromOpcode('i', $edit->getText(), 0, $edit->getToLen());
            } else {
                /* if ( $edit instanceof FineDiffReplaceOp ) */
                FineDiff::renderDiffToHTMLFromOpcode('d', $this->fromText, $inOffset, $n);
                FineDiff::renderDiffToHTMLFromOpcode('i', $edit->getText(), 0, $edit->getToLen());
            }
            $inOffset += $n;
        }
        return ob_get_clean();
    }

    /**------------------------------------------------------------------------
     * Return an opcodes string describing the diff between a "From" and a
     * "To" string
     */
    public static function getDiffOpcodes($from, $to, $granularities = null)
    {
        $diff = new FineDiff($from, $to, $granularities);
        return $diff->getOpcodes();
    }

    /**------------------------------------------------------------------------
     * Return an iterable collection of diff ops from an opcodes string
     */
    public static function getDiffOpsFromOpcodes($opcodes)
    {
        $diffops = new FineDiffOps();
        FineDiff::renderFromOpcodes(null, $opcodes, array($diffops, 'appendOpcode'));
        return $diffops->edits;
    }

    /**------------------------------------------------------------------------
     * Re-create the "To" string from the "From" string and an "Opcodes" string
     */
    public static function renderToTextFromOpcodes($from, $opcodes)
    {
        ob_start();
        FineDiff::renderFromOpcodes($from, $opcodes, array('FineDiff', 'renderToTextFromOpcode'));
        return ob_get_clean();
    }

    /**------------------------------------------------------------------------
     * Render the diff to an HTML string
     */
    public static function renderDiffToHTMLFromOpcodes($from, $opcodes)
    {
        ob_start();
        FineDiff::renderFromOpcodes($from, $opcodes, array('FineDiff', 'renderDiffToHTMLFromOpcode'));
        return ob_get_clean();
    }

    /**------------------------------------------------------------------------
     * Generic opcodes parser, user must supply callback for handling
     * single opcode
     */
    public static function renderFromOpcodes($from, $opcodes, $callback)
    {
        if (!is_callable($callback)) {
            return;
        }
        $opcodesLen = strlen($opcodes);
        $fromOffset = $opcodesOffset = 0;
        while ($opcodesOffset < $opcodesLen) {
            $opcode = substr($opcodes, $opcodesOffset, 1);
            $opcodesOffset++;
            $n = intval(substr($opcodes, $opcodesOffset));
            if ($n) {
                $opcodesOffset += strlen(strval($n));
            } else {
                $n = 1;
            }
            if ($opcode === 'c') { // copy n characters from source
                call_user_func($callback, 'c', $from, $fromOffset, $n, '');
                $fromOffset += $n;
            } elseif ($opcode === 'd') { // delete n characters from source
                call_user_func($callback, 'd', $from, $fromOffset, $n, '');
                $fromOffset += $n;
            } else {
                /* if ( $opcode === 'i' ) */
                // insert n characters from opcodes
                call_user_func($callback, 'i', $opcodes, $opcodesOffset + 1, $n);
                $opcodesOffset += 1 + $n;
            }
        }
    }

    /**
     * Stock granularity stacks and delimiters
     */

    const PARAGRAPH_DELIMITERS = "\n\r";
    public static $paragraphGranularity = array(
        FineDiff::PARAGRAPH_DELIMITERS,
    );
    const SENTENCE_DELIMITERS = ".\n\r";
    public static $sentenceGranularity = array(
        FineDiff::PARAGRAPH_DELIMITERS,
        FineDiff::SENTENCE_DELIMITERS,
    );
    const WORD_DELIMITERS = " \t.\n\r";
    public static $wordGranularity = array(
        FineDiff::PARAGRAPH_DELIMITERS,
        FineDiff::SENTENCE_DELIMITERS,
        FineDiff::WORD_DELIMITERS,
    );
    const CHARACTERS_DELIMITERS = "";
    public static $characterGranularity = array(
        FineDiff::PARAGRAPH_DELIMITERS,
        FineDiff::SENTENCE_DELIMITERS,
        FineDiff::WORD_DELIMITERS,
        FineDiff::CHARACTERS_DELIMITERS,
    );

    public static $textStack = array(
        ".",
        " \t.\n\r",
        "",
    );

    /**------------------------------------------------------------------------
     *
     * Private section
     *
     */

    /**
     * Entry point to compute the diff.
     */
    private function doDiff($fromText, $toText)
    {
        $this->lastEdit = false;
        $this->stackpointer = 0;
        $this->fromText = $fromText;
        $this->fromOffset = 0;
        // can't diff without at least one granularity specifier
        if (empty($this->granularityStack)) {
            return;
        }
        $this->_processGranularity($fromText, $toText);
    }

    /**
     * This is the recursive function which is responsible for
     * handling/increasing granularity.
     *
     * Incrementally increasing the granularity is key to compute the
     * overall diff in a very efficient way.
     */
    private function _processGranularity($fromSegment, $toSegment)
    {
        $delimiters = $this->granularityStack[$this->stackpointer++];
        $hasNextStage = $this->stackpointer < count($this->granularityStack);
        foreach (FineDiff::doFragmentDiff($fromSegment, $toSegment, $delimiters) as $fragment_edit) {
            // increase granularity
            if ($fragment_edit instanceof FineDiffReplaceOp && $hasNextStage) {
                $this->_processGranularity(
                    substr($this->fromText, $this->fromOffset, $fragment_edit->getFromLen()),
                    $fragment_edit->getText()
                );
			} elseif ($fragment_edit instanceof FineDiffCopyOp && $this->lastEdit instanceof FineDiffCopyOp) {
				// fuse copy ops whenever possible
                $this->edits[count($this->edits) - 1]->increase($fragment_edit->getFromLen());
                $this->fromOffset += $fragment_edit->getFromLen();
            } else {
                /* $fragment_edit instanceof FineDiffCopyOp */
                /* $fragment_edit instanceof FineDiffDeleteOp */
                /* $fragment_edit instanceof FineDiffInsertOp */
                $this->edits[] = $this->lastEdit = $fragment_edit;
                $this->fromOffset += $fragment_edit->getFromLen();
            }
        }
        $this->stackpointer--;
    }

    /**
     * This is the core algorithm which actually perform the diff itself,
     * fragmenting the strings as per specified delimiters.
     *
     * This function is naturally recursive, however for performance purpose
     * a local job queue is used instead of outright recursivity.
     */
    private static function doFragmentDiff($fromText, $toText, $delimiters)
    {
        // Empty delimiter means character-level diffing.
        // In such case, use code path optimized for character-level
        // diffing.
        if (empty($delimiters)) {
            return FineDiff::doCharDiff($fromText, $toText);
        }

        $result = array();

        // fragment-level diffing
        $fromTextLen = strlen($fromText);
        $toTextLen = strlen($toText);
        $fromFragments = FineDiff::extractFragments($fromText, $delimiters);
        $toFragments = FineDiff::extractFragments($toText, $delimiters);

        $jobs = array(array(0, $fromTextLen, 0, $toTextLen));

        $cachedArrayKeys = array();

        while ($job = array_pop($jobs)) {

            // get the segments which must be diff'ed
            list($fromSegmentStart, $fromSegmentEnd, $toSegmentStart, $toSegmentEnd) = $job;

            // catch easy cases first
            $fromSegmentLength = $fromSegmentEnd - $fromSegmentStart;
            $toSegmentLength = $toSegmentEnd - $toSegmentStart;
            if (!$fromSegmentLength || !$toSegmentLength) {
                if ($fromSegmentLength) {
                    $result[$fromSegmentStart * 4] = new FineDiffDeleteOp($fromSegmentLength);
                } elseif ($toSegmentLength) {
                    $result[$fromSegmentStart * 4 + 1] =
						new FineDiffInsertOp(substr(
							$toText,
							$toSegmentStart,
							$toSegmentLength
						)
					);
                }
                continue;
            }

            // find longest copy operation for the current segments
            $bestCopyLength = 0;

            $fromBaseFragmentIndex = $fromSegmentStart;

            $cachedArrayKeysForCurrentSegment = array();

            while ($fromBaseFragmentIndex < $fromSegmentEnd) {
                $fromBaseFragment = $fromFragments[$fromBaseFragmentIndex];
                $fromBaseFragmentLength = strlen($fromBaseFragment);
                // performance boost: cache array keys
                if (!isset($cachedArrayKeysForCurrentSegment[$fromBaseFragment])) {
                    if (!isset($cachedArrayKeys[$fromBaseFragment])) {
                        $toAllFragmentIndices =
							$cachedArrayKeys[$fromBaseFragment] =
								array_keys($toFragments, $fromBaseFragment, true);
                    } else {
                        $toAllFragmentIndices = $cachedArrayKeys[$fromBaseFragment];
                    }
                    // get only indices which falls within current segment
                    if ($toSegmentStart > 0 || $toSegmentEnd < $toTextLen) {
                        $toFragmentIndices = array();
                        foreach ($toAllFragmentIndices as $toFragmentIndex) {
                            if ($toFragmentIndex < $toSegmentStart) {continue;}
                            if ($toFragmentIndex >= $toSegmentEnd) {break;}
                            $toFragmentIndices[] = $toFragmentIndex;
                        }
                        $cachedArrayKeysForCurrentSegment[$fromBaseFragment] = $toFragmentIndices;
                    } else {
                        $toFragmentIndices = $toAllFragmentIndices;
                    }
                } else {
                    $toFragmentIndices = $cachedArrayKeysForCurrentSegment[$fromBaseFragment];
                }
                // iterate through collected indices
                foreach ($toFragmentIndices as $to_base_fragment_index) {
                    $fragmentIndexOffset = $fromBaseFragmentLength;
                    // iterate until no more match
                    while (true) {
                        $fragmentFromIndex = $fromBaseFragmentIndex + $fragmentIndexOffset;
                        if ($fragmentFromIndex >= $fromSegmentEnd) {
                            break;
                        }
                        $fragmentToIndex = $to_base_fragment_index + $fragmentIndexOffset;
                        if ($fragmentToIndex >= $toSegmentEnd) {
                            break;
                        }
                        if ($fromFragments[$fragmentFromIndex] !== $toFragments[$fragmentToIndex]) {
                            break;
                        }
                        $fragmentLength = strlen($fromFragments[$fragmentFromIndex]);
                        $fragmentIndexOffset += $fragmentLength;
                    }
                    if ($fragmentIndexOffset > $bestCopyLength) {
                        $bestCopyLength = $fragmentIndexOffset;
                        $bestFromStart = $fromBaseFragmentIndex;
                        $bestToStart = $to_base_fragment_index;
                    }
                }
                $fromBaseFragmentIndex += strlen($fromBaseFragment);
                // If match is larger than half segment size, no point trying to find better
                // TODO: Really?
                if ($bestCopyLength >= $fromSegmentLength / 2) {
                    break;
                }
                // no point to keep looking if what is left is less than
                // current best match
                if ($fromBaseFragmentIndex + $bestCopyLength >= $fromSegmentEnd) {
                    break;
                }
            }

            if ($bestCopyLength) {
                $jobs[] = array($fromSegmentStart, $bestFromStart, $toSegmentStart, $bestToStart);
                $result[$bestFromStart * 4 + 2] = new FineDiffCopyOp($bestCopyLength);
                $jobs[] = array(
					$bestFromStart + $bestCopyLength,
					$fromSegmentEnd,
					$bestToStart + $bestCopyLength,
					$toSegmentEnd
				);
            } else {
                $result[$fromSegmentStart * 4] = new FineDiffReplaceOp(
					$fromSegmentLength,
					substr(
						$toText,
						$toSegmentStart,
						$toSegmentLength
					)
				);
            }
        }

        ksort($result, SORT_NUMERIC);
        return array_values($result);
    }

    /**
     * Perform a character-level diff.
     *
     * The algorithm is quite similar to doFragmentDiff(), except that
     * the code path is optimized for character-level diff -- strpos() is
     * used to find out the longest common subequence of characters.
     *
     * We try to find a match using the longest possible subsequence, which
     * is at most the length of the shortest of the two strings, then incrementally
     * reduce the size until a match is found.
     *
     * I still need to study more the performance of this function. It
     * appears that for long strings, the generic doFragmentDiff() is more
     * performant. For word-sized strings, doCharDiff() is somewhat more
     * performant.
     */
    private static function doCharDiff($fromText, $toText)
    {
        $result = array();
        $jobs = array(array(0, strlen($fromText), 0, strlen($toText)));
        while ($job = array_pop($jobs)) {
            // get the segments which must be diff'ed
            list($fromSegmentStart, $fromSegmentEnd, $toSegmentStart, $toSegmentEnd) = $job;
            $fromSegmentLen = $fromSegmentEnd - $fromSegmentStart;
            $toSegmentLen = $toSegmentEnd - $toSegmentStart;

            // catch easy cases first
            if (!$fromSegmentLen || !$toSegmentLen) {
                if ($fromSegmentLen) {
                    $result[$fromSegmentStart * 4 + 0] = new FineDiffDeleteOp($fromSegmentLen);
                } elseif ($toSegmentLen) {
                    $result[$fromSegmentStart * 4 + 1] = new FineDiffInsertOp(
						substr(
							$toText,
							$toSegmentStart,
							$toSegmentLen
						)
					);
                }
                continue;
            }
            if ($fromSegmentLen >= $toSegmentLen) {
                $copyLen = $toSegmentLen;
                while ($copyLen) {
                    $toCopyStart = $toSegmentStart;
                    $toCopyStartMax = $toSegmentEnd - $copyLen;
                    while ($toCopyStart <= $toCopyStartMax) {
                        $fromCopyStart = strpos(
							substr(
								$fromText,
								$fromSegmentStart,
								$fromSegmentLen
							),
							substr(
								$toText,
								$toCopyStart,
								$copyLen
							)
						);
                        if ($fromCopyStart !== false) {
                            $fromCopyStart += $fromSegmentStart;
                            break 2;
                        }
                        $toCopyStart++;
                    }
                    $copyLen--;
                }
            } else {
                $copyLen = $fromSegmentLen;
                while ($copyLen) {
                    $fromCopyStart = $fromSegmentStart;
                    $fromCopyStartMax = $fromSegmentEnd - $copyLen;
                    while ($fromCopyStart <= $fromCopyStartMax) {
                        $toCopyStart = strpos(
							substr(
								$toText,
								$toSegmentStart,
								$toSegmentLen
							),
							substr(
								$fromText,
								$fromCopyStart,
								$copyLen
							)
						);
                        if ($toCopyStart !== false) {
                            $toCopyStart += $toSegmentStart;
                            break 2;
                        }
                        $fromCopyStart++;
                    }
                    $copyLen--;
                }
            }
            // match found
            if ($copyLen) {
                $jobs[] = array($fromSegmentStart, $fromCopyStart, $toSegmentStart, $toCopyStart);
                $result[$fromCopyStart * 4 + 2] = new FineDiffCopyOp($copyLen);
                $jobs[] = array($fromCopyStart + $copyLen, $fromSegmentEnd, $toCopyStart + $copyLen, $toSegmentEnd);
            } else {
			// no match,  so delete all, insert all
                $result[$fromSegmentStart * 4] = new FineDiffReplaceOp(
					$fromSegmentLen,
					substr(
						$toText,
						$toSegmentStart,
						$toSegmentLen
					)
				);
            }
        }
        ksort($result, SORT_NUMERIC);
        return array_values($result);
    }

    /**
     * Efficiently fragment the text into an array according to
     * specified delimiters.
     * No delimiters means fragment into single character.
     * The array indices are the offset of the fragments into
     * the input string.
     * A sentinel empty fragment is always added at the end.
     * Careful: No check is performed as to the validity of the
     * delimiters.
     */
    private static function extractFragments($text, $delimiters)
    {
        // special case: split into characters
        if (empty($delimiters)) {
            $chars = str_split($text, 1);
            $chars[strlen($text)] = '';
            return $chars;
        }
        $fragments = array();
        $start = $end = 0;
        while (true) {
            $end += strcspn($text, $delimiters, $end);
            $end += strspn($text, $delimiters, $end);
            if ($end === $start) {
                break;
            }
            $fragments[$start] = substr($text, $start, $end - $start);
            $start = $end;
        }
        $fragments[$start] = '';
        return $fragments;
    }

    /**
     * Stock opcode renderers
     */
    private static function renderToTextFromOpcode($opcode, $from, $fromOffset, $fromLen)
    {
        if ($opcode === 'c' || $opcode === 'i') {
            echo substr($from, $fromOffset, $fromLen);
        }
    }

    private static function renderDiffToHTMLFromOpcode($opcode, $from, $fromOffset, $fromLen)
    {
        if ($opcode === 'c') {
            echo htmlentities(substr($from, $fromOffset, $fromLen));
        } elseif ($opcode === 'd') {
            $deletion = substr($from, $fromOffset, $fromLen);
            if (strcspn($deletion, " \n\r") === 0) {
                $deletion = str_replace(array("\n", "\r"), array('\n', '\r'), $deletion);
            }
            echo '<del>', htmlentities($deletion), '</del>';
        } else {
			/* if ( $opcode === 'i' ) */
            echo '<ins>', htmlentities(substr($from, $fromOffset, $fromLen)), '</ins>';
        }
    }
}
