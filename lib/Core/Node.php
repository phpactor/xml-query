<?php

namespace Phpactor\XmlEditor\Core;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Phpactor\XmlEditor\Core\Exception\NodeHasNoParent;
use Phpactor\XmlEditor\Core\Exception\CannotReplaceRoot;
use RuntimeException;

class Node implements NodeLike
{
    /**
     * @var DOMNode
     */
    private $node;

    public function __construct(DOMNode $node)
    {
        $this->node = $node;
    }

    public static function fromDOMNode(DOMNode $node): Node
    {
        return new self($node);
    }

    public static function fromXml(string $string): Node
    {
        $dom = new DOMDocument('1.0');
        $dom->loadXML($string);

        return new self($dom);
    }

    public static function fromXmlFirstChild($node): Node
    {
        $node = self::fromXml($node);

        return new self($node->node->firstChild);
    }

    public static function fromUnknown($node): Node
    {
        if ($node instanceof Node) {
            return $node;
        }

        if (is_scalar($node)) {
            return Node::fromXMLFirstChild($node);
        }

        if ($node instanceof DOMNode) {
            return new self($node);
        }

        throw new RuntimeException(sprintf(
            'Node argument must be either a Node, an XML string or a DOMNode, got "%s"',
            is_object($node) ? get_class($node) : gettype($object)
        ));
    }

    public function find(string $xpathQuery): NodeList
    {
        $xpath = new DOMXPath($this->root()->node);

        return NodeList::fromDOMNodeList($xpath->query($xpathQuery, $this->node));
    }

    public function parent(): Node
    {
        if (null === $this->node->parentNode) {
            throw new NodeHasNoParent(sprintf(
                'Node "%s" has no parent'
            , $this->name()));
        }

        return new self($this->node->parentNode);
    }

    public function name(): string
    {
        return $this->node->nodeName;
    }

    public function remove(): void
    {
        $this->node->parentNode->removeChild($this->node);
    }

    /**
     * {@inheritDoc}
     */
    public function text(): string
    {
        return $this->node->textContent;
    }

    /**
     * {@inheritDoc}
     * @return Node
     */
    public function replace($node): NodeLike
    {
        $node = self::fromUnknown($node);

        if ($this->node instanceof DOMDocument) {
            throw new CannotReplaceRoot(
                'Cannot replace root node (DOMDocument)'
            );
        }

        $newNode = $this->root()->node->importNode($node->node, true);
        $this->node->parentNode->replaceChild($newNode, $this->node);
        $this->node = $newNode;

        return $this;
    }

    public function dump($pretty = false): string
    {
        if (!$this->node instanceof DOMDocument) {
            $domDocument = new DOMDocument();
            $node = $domDocument->importNode($this->node, true);
            $domDocument->appendChild($node);
        } else {
            $domDocument = $this->node;
        }

        if ($pretty) {
            $domDocument->formatOutput = true;
            $domDocument->preserveWhiteSpace = true;
        }

        return $domDocument->saveXML();
    }

    /**
     * {@inheritDoc}
     * @return Node
     */
    public function before($node): NodeLike
    {
        $newNode = $this->importUnknown($node);

        $parent = $this->node->parentNode;
        $parent->insertBefore($newNode, $this->node);

        return $this;
    }

    /**
     * {@inheritDoc}
     * @return Node
     */
    public function after($node): NodeLike
    {
        $newNode = $this->importUnknown($node);

        $parent = $this->node->parentNode;
        $parent->insertBefore($newNode, $this->node->nextSibling);

        return $this;
    }

    /**
     * {@inheritDoc}
     * @return Node
     */
    public function append($node): NodeLike
    {
        $newNode = $this->importUnknown($node);
        $this->node->appendChild($newNode);

        return $this;
    }

    /**
     * {@inheritDoc}
     * @return Node
     */
    public function prepend($node): NodeLike
    {
        $newNode = $this->importUnknown($node);
        $this->node->insertBefore($newNode, $this->node->firstChild);

        return $this;
    }

    /**
     * @return Node
     */
    public function clear(): NodeLike
    {
        // DOMNodeList does seem to itreate over all the children ಠ_ಠ
        while ($this->node->childNodes->length) {
            foreach ($this->node->childNodes as $childNode) {
                $this->node->removeChild($childNode);
            }
        }

        return $this;
    }

    /**
     * @return Node
     */
    public function root(): Node
    {
        if ($this->node instanceof DOMDocument) {
            return $this;
        }

        return new Node($this->node->ownerDocument);
    }

    public function children(): NodeList
    {
        return NodeList::fromDOMNodeList($this->node->childNodes);
    }

    private function importUnknown($node)
    {
        $node = Node::fromUnknown($node);
        $newNode = $this->node->ownerDocument->importNode($node->node);
        return $newNode;
    }
}
