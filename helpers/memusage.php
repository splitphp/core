<?php

namespace SplitPHP\Helpers;

class MemUsage
{

  public function logMemory(string $label, int $abortIfOver = 128* 1024 * 1024)
  {
    $usage = memory_get_usage(true);
    $peak  = memory_get_peak_usage(true);
    echo str_pad($label, 30) .
      ' | Current: ' . str_pad($this->formatBytes($usage), 10) .
      ' | Peak: ' . str_pad($this->formatBytes($peak), 10) . PHP_EOL;

    if ($abortIfOver > 0 && $usage > $abortIfOver) {
      echo "🚫 Abortando: memória excedeu " . $this->formatBytes($abortIfOver) . PHP_EOL;
      exit(1);
    }
  }

  private function formatBytes($bytes)
  {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 2) . ' KB';
    if ($bytes < 1073741824) return round($bytes / 1048576, 2) . ' MB';
    return round($bytes / 1073741824, 2) . ' GB';
  }
}
