<?php

declare(strict_types=1);

namespace SOFe\Capital\Di;

use RuntimeException;
use function fclose;
use function fopen;
use function fwrite;
use function sprintf;
use function str_replace;
use function strrpos;
use function substr;

final class DepGraphWriter {
    /** @var array<string, array<string, string>> */
    private array $clusters = [];

    /** @var array<string, array<string, true>> */
    private array $edges = [];

    /** @var array<string, float> */
    private array $nodeTime = [];

    public function addNode(string $class, ?float $time = null) : string {
        $id = str_replace("\\", "__", $class);

        $nsLen = strrpos($class, "\\");
        if($nsLen !== false) {
            $ns = substr($class, 0, $nsLen);
            $disp = substr($class, $nsLen + 1);
        } else {
            $ns = "";
            $disp = $class;
        }

        if(!isset($this->clusters[$ns])) {
            $this->clusters[$ns] = [];
        }
        $this->clusters[$ns][$id] = $disp;

        if($time !== null) {
            $this->nodeTime[$id] = $time;
        }

        return $id;
    }

    public function addEdge(string $from, string $to) : void {
        $fromId = $this->addNode($from);
        $toId = $this->addNode($to);

        if(!isset($this->edges[$fromId])) {
            $this->edges[$fromId] = [];
        }
        $this->edges[$fromId][$toId] = true;
    }

    public function write(string $file) : void {
        $f = fopen($file, "w");
        if($f === false) {
            throw new RuntimeException("Error opening $file");
        }

        try {
            fwrite($f, "digraph G {\n");
            fwrite($f, "graph [ranksep=0.6, nodesep=0.3]\n");

            $i = 0;
            foreach($this->clusters as $ns => $nodes) {
                ++$i;
                fwrite($f, "subgraph cluster_$i {\n");

                $label = "\"" . str_replace("\\", "\\\\", $ns) . "\"";
                fwrite($f, "label = $label;\n");

                foreach($nodes as $id => $disp) {
                    $label = str_replace("\\", "\\\\", $disp);
                    if(isset($this->nodeTime[$id])) {
                        $label .= sprintf("\n%g ms", $this->nodeTime[$id] * 1000.0);
                    }
                    fwrite($f, "$id [label = \"$label\", shape = none];\n");
                }
                fwrite($f, "}\n");
            }

            foreach($this->edges as $from => $edges) {
                foreach($edges as $to => $_) {
                    fwrite($f, "$from -> $to [color = \"#AAAAAA\"];\n");
                }
            }

            fwrite($f, "}\n");
        } finally {
            fclose($f);
        }
    }
}
