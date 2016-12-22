<?php

namespace PackageFactory\AtomicFusion\Aspect;

use TYPO3\Flow\Annotations as Flow;

/**
 * @Flow\Aspect
 */
class FusionParserAspect
{

    protected $namespaces = [];

    protected $namespaceDeclarations = '';

    /**
     * Around advice
     * @Flow\Around("method(TYPO3\TypoScript\Core\Parser->parse())")
     */
    public function expandJsxToFusion(\TYPO3\Flow\AOP\JoinPointInterface $joinPoint)
    {
        $fusionAst = $joinPoint->getAdviceChain()->proceed($joinPoint);
        $namespaces = [];

        foreach ($fusionAst['__prototypes'] as $prototypeName => $prototypeConfiguration) {
            list($namspace, $name) = explode(':', $prototypeName, 2);
            $namespaces[] = $namspace;
        }
        $this->namespaces = array_unique($namespaces);
        $this->namespaceDeclarations = array_reduce($this->namespaces, function($carry, $item){ return $carry . ' xmlns:' . $item . '="http://example.com"'; } );

        $this->postProcessFusionObjectTree($fusionAst);
        return $fusionAst;
    }


    /**
     * Post processes the given fusion tree
     *
     * @param array &$fusionObjectTree
     * @return void
     */
    protected function postProcessFusionObjectTree(array &$fusionObjectTree)
    {
        foreach ($fusionObjectTree as $key => $value) {
            if (is_array($value)) {
                $this->postProcessFusionObjectTree($fusionObjectTree[$key]);
            }
            if (is_string($value) && substr($value, 0, 4) == '>>>' . PHP_EOL) {
                $fusionObjectTree[$key] = $this->replaceJsxInValue($value);
            }
        }
    }

    /**
     * @param $value
     * @return mixed
     */
    protected function replaceJsxInValue ($value) {
        $xmlString = '<xml ' . $this->namespaceDeclarations . '>';
        $xmlString .= substr($value, 4);
        $xmlString .= '</xml>';
        $xml = new \SimpleXMLElement($xmlString);
        return $this->convertSimpleXmlToFusionAst($xml);
    }

    /**
     * @param \SimpleXMLElement $xml
     * @return array
     */
    protected function convertSimpleXmlToFusionAst(\SimpleXMLElement $xml) {
        $result = [
            '__objectType' => null,
            '__value' => null,
            '__eelExpression' => null
        ];

        $name = 'foo'; // $xml->getName();

        if (strpos($name, ':') === FALSE) {
            $result['__objectType'] = 'TYPO3.TypoScript:Tag';
            $result['tagName'] = $name;
        } else {
            $result['__objectType'] = $name;
        }

        return $result;
    }

}
