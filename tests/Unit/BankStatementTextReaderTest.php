<?php

namespace Tests\Unit;

use App\Services\Accounting\BankReconciliation\Statement\BankStatementTextReader;
use Dompdf\Dompdf;
use InvalidArgumentException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BankStatementTextReaderTest extends TestCase
{
    public function test_decrypts_secured_pdf_with_qpdf_and_matches_plain_text(): void
    {
        $qpdf = $this->resolveQpdfBinary();
        if ($qpdf === null) {
            $this->markTestSkipped('qpdf binary is not available on this machine.');

            return;
        }

        $directory = storage_path('app/bank-reconciliation/tmp-test');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $plainPath = $directory.'/plain.pdf';
        $encryptedPath = $directory.'/encrypted.pdf';

        $this->writeFixturePdf($plainPath);

        $encrypt = new Process([
            $qpdf,
            '--encrypt',
            '',
            '',
            '256',
            '--',
            $plainPath,
            $encryptedPath,
        ]);
        $encrypt->setTimeout(60);
        $encrypt->run();

        if (! $encrypt->isSuccessful()) {
            $this->markTestSkipped('qpdf could not encrypt the fixture PDF: '.$encrypt->getErrorOutput());
        }

        config(['bank_reconciliation.statement_qpdf_binary' => $qpdf]);

        $reader = new BankStatementTextReader;
        $plainText = $reader->read($plainPath);
        $encryptedText = $reader->read($encryptedPath);

        $this->assertStringContainsString('BankStatementFixtureText', $plainText);
        $this->assertSame($plainText, $encryptedText);

        @unlink($plainPath);
        @unlink($encryptedPath);
    }

    public function test_missing_qpdf_binary_throws_clear_error(): void
    {
        $directory = storage_path('app/bank-reconciliation/tmp-test');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $plainPath = $directory.'/secured-trigger.pdf';
        $this->writeFixturePdf($plainPath);

        $qpdf = $this->resolveQpdfBinary();
        if ($qpdf === null) {
            $this->markTestSkipped('qpdf binary is not available on this machine.');

            return;
        }

        $encryptedPath = $directory.'/secured-trigger-encrypted.pdf';
        $encrypt = new Process([$qpdf, '--encrypt', '', '', '256', '--', $plainPath, $encryptedPath]);
        $encrypt->run();

        if (! $encrypt->isSuccessful()) {
            $this->markTestSkipped('qpdf could not encrypt the fixture PDF.');
        }

        config(['bank_reconciliation.statement_qpdf_binary' => '/nonexistent/bank-rec-qpdf']);

        $reader = new BankStatementTextReader;

        try {
            $reader->read($encryptedPath);
            $this->fail('Expected InvalidArgumentException when qpdf binary is missing.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('qpdf', strtolower($exception->getMessage()));
            $this->assertStringNotContainsString('python', strtolower($exception->getMessage()));
            $this->assertStringNotContainsString('pypdf', strtolower($exception->getMessage()));
        } finally {
            @unlink($plainPath);
            @unlink($encryptedPath);
        }
    }

    private function writeFixturePdf(string $absolutePath): void
    {
        $dompdf = new Dompdf;
        $dompdf->loadHtml('<p>BankStatementFixtureText</p>');
        $dompdf->render();
        file_put_contents($absolutePath, $dompdf->output());
    }

    private function resolveQpdfBinary(): ?string
    {
        $configured = (string) config('bank_reconciliation.statement_qpdf_binary', 'qpdf');

        if ($configured !== 'qpdf' && is_executable($configured)) {
            return $configured;
        }

        $process = new Process(['which', 'qpdf']);
        $process->run();

        if ($process->isSuccessful()) {
            return trim($process->getOutput());
        }

        return is_executable('/usr/bin/qpdf') ? '/usr/bin/qpdf' : null;
    }
}
