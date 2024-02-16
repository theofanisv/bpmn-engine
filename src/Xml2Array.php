<?php

namespace Theograms\BpmnManager;

use XMLParser;

class Xml2Array
{
    private array $output = [];

    private function __construct(private string $xml_string)
    {
    }

    public static function toArray(string $xml): array
    {
        return (new static($xml))->parse()->output;
    }

    private function getParser(): XMLParser
    {
        $parser = xml_parser_create();
        xml_set_object($parser, $this);
        xml_set_element_handler($parser, $this->tagOpen(...), $this->tagClosed(...));
        xml_set_character_data_handler($parser, $this->tagData(...));
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);

        return $parser;
    }

    private function parse(): static
    {
        $parser = $this->getParser();
        $parsed = xml_parse($parser, $this->xml_string);
        throw_unless($parsed, \Exception::class, sprintf('XML error: %s at line %d', xml_error_string(xml_get_error_code($parser)), xml_get_current_line_number($parser)));
        xml_parser_free($parser);

        return $this;
    }

    private function tagOpen(XMLParser $parser, $name, $attrs): void
    {
        $this->output[] = ['tag' => $name] + $attrs;
    }

    private function tagData(XMLParser $parser, $tag_data): void
    {
        $tag_data = trim($tag_data);
        if (empty($tag_data)) {
            return;
        }

        if (isset($this->output[count($this->output) - 1]['body'])) {
            $this->output[count($this->output) - 1]['body'] .= $tag_data;
        } else {
            $this->output[count($this->output) - 1]['body'] = $tag_data;
        }
    }

    private function tagClosed(XMLParser $parser, $name): void
    {
        $this->output[count($this->output) - 2]['children'][] = $this->output[count($this->output) - 1];
        array_pop($this->output);
    }
}
