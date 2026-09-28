<?php

namespace App\Services\Accounting\BankReconciliation\Statement;

use InvalidArgumentException;
use Smalot\PdfParser\Parser;
use Symfony\Component\Process\Process;

class BankStatementTextReader
{
    public function read(string $absolutePath): string
    {
        if (! is_readable($absolutePath)) {
            throw new InvalidArgumentException('Bank statement file is not readable.');
        }

        $pathForParser = $absolutePath;
        $temporaryPath = null;

        try {
            return $this->extractText($pathForParser);
        } catch (\Exception $exception) {
            if (! str_contains($exception->getMessage(), 'Secured pdf')) {
                throw $exception;
            }

            $temporaryPath = $this->decryptSecuredPdfToTemporary($absolutePath);
            $pathForParser = $temporaryPath;

            return $this->extractText($pathForParser);
        } finally {
            if ($temporaryPath !== null && is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    private function extractText(string $absolutePath): string
    {
        $parser = new Parser;
        $document = $parser->parseFile($absolutePath);
        $pages = $document->getPages();
        $chunks = [];

        foreach ($pages as $page) {
            $chunks[] = trim((string) $page->getText());
        }

        return implode("\f", $chunks);
    }

    private function decryptSecuredPdfToTemporary(string $absolutePath): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'bank_stmt_').'.pdf';

        $script = <<<'PY'
import sys
from pypdf import PdfReader, PdfWriter

source, dest = sys.argv[1], sys.argv[2]
reader = PdfReader(source)
if reader.is_encrypted:
    reader.decrypt("")
writer = PdfWriter()
for page in reader.pages:
    writer.add_page(page)
with open(dest, "wb") as handle:
    writer.write(handle)
PY;

        $process = new Process(['python3', '-c', $script, $absolutePath, $temporaryPath]);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful() || ! is_readable($temporaryPath)) {
            @unlink($temporaryPath);

            throw new InvalidArgumentException(
                'This PDF is password-protected and could not be decrypted for text extraction. '
                .'Install Python pypdf on the server or upload an unencrypted export.',
            );
        }

        return $temporaryPath;
    }
}
