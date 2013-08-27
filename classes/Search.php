<?php

class PHPIntel_Search
{
    protected $visitor;

    public function __construct(PHPParser_NodeVisitor $visitor) {
        $this->visitor = $visitor;
    }

    public function traverse(array $nodes) {
        $this->visitor->beforeTraverse($nodes);
        $this->traverseArray($nodes);
        $this->visitor->afterTraverse($nodes);
    }

    protected function traverseNode(PHPParser_Node $node) {
        foreach ($node->getSubNodeNames() as $name) {
            $subNode =& $node->$name;

            if (is_array($subNode)) {
                $subNode = $this->traverseArray($subNode);
            } elseif ($subNode instanceof PHPParser_Node) {
                if ($this->visitor->enterNode($subNode) === false) {
                    continue;
                }
                $this->traverseNode($subNode);
                $this->visitor->leaveNode($subNode);
            }
        }
    }

    protected function traverseArray(array $nodes) {
        $doNodes = array();

        foreach ($nodes as $i => &$node) {
            if (is_array($node)) {
                $node = $this->traverseArray($node);
            } elseif ($node instanceof PHPParser_Node) {
                if ($this->visitor->enterNode($node) === false) {
                    continue;
                }
                $this->traverseNode($node);
                $this->visitor->leaveNode($node);
            }
        }
    }
}