<?php

declare(strict_types=1);

require_once __DIR__ . '/fpdf/fpdf.php';
require_once __DIR__ . '/bootstrap.php';

/**
 * Descompacta o campo "cues" em partes estruturadas.
 * Replica a logica de training_parse_cues em area-admin-treinos.php.
 */
function training_pdf_parse_cues(?string $cues): array
{
    $base = ['series' => '', 'reps' => '', 'load' => '', 'notes' => '', 'order' => ''];
    if (!$cues) {
        return $base;
    }
    $decoded = json_decode($cues, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        return array_merge($base, array_intersect_key($decoded, $base));
    }
    $base['notes'] = trim($cues);
    return $base;
}

/**
 * Converte texto UTF-8 para Windows-1252 (encoding nativo do FPDF).
 */
function training_pdf_encode(string $text): string
{
    $encoded = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
    return $encoded !== false ? $encoded : $text;
}

/**
 * PDF customizado com cabecalho e rodape NutremFit.
 */
class TrainingPDF extends FPDF
{
    public string $studentName = '';
    public string $studentEmail = '';
    public string $trainingTitle = '';

    function Header(): void
    {
        // Fundo do cabecalho
        $this->SetFillColor(11, 15, 26);
        $this->Rect(0, 0, 210, 38, 'F');

        // Logo / titulo
        $this->SetFont('Helvetica', 'B', 18);
        $this->SetTextColor(255, 107, 53);
        $this->SetXY(15, 8);
        $this->Cell(0, 8, training_pdf_encode('NutremFit'), 0, 1, 'L');

        // Subtitulo
        $this->SetFont('Helvetica', '', 10);
        $this->SetTextColor(200, 200, 200);
        $this->SetX(15);
        $this->Cell(0, 5, training_pdf_encode('Ficha de Treino'), 0, 1, 'L');

        // Data de geracao (lado direito)
        $this->SetXY(120, 8);
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(160, 160, 160);
        $this->Cell(75, 5, training_pdf_encode('Gerado em: ' . date('d/m/Y H:i')), 0, 1, 'R');

        // Info do aluno
        $this->SetXY(120, 14);
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(200, 200, 200);
        $aluno = $this->studentName ?: $this->studentEmail;
        $this->Cell(75, 5, training_pdf_encode('Aluno: ' . $aluno), 0, 1, 'R');

        $this->Ln(8);
    }

    function Footer(): void
    {
        $this->SetY(-15);
        $this->SetFont('Helvetica', 'I', 8);
        $this->SetTextColor(140, 140, 140);
        $this->Cell(0, 10, training_pdf_encode('Gerado automaticamente por NutremFit - ' . date('d/m/Y H:i')), 0, 0, 'L');
        $this->Cell(0, 10, training_pdf_encode('Pg ' . $this->PageNo() . '/{nb}'), 0, 0, 'R');
    }
}

/**
 * Gera o PDF do treino e retorna o conteudo binario.
 *
 * @param array $plan Dados do treino (retorno de training_store_find_for_user)
 * @param array $user Dados do usuario (retorno de user_store_find)
 * @return string Conteudo binario do PDF
 */
function training_generate_pdf(array $plan, array $user): string
{
    $pdf = new TrainingPDF('P', 'mm', 'A4');
    $pdf->studentName = trim((string) ($user['name'] ?? ''));
    $pdf->studentEmail = trim((string) ($user['email'] ?? ''));
    $pdf->trainingTitle = trim((string) ($plan['title'] ?? 'Ficha de Treino'));
    $pdf->AliasNbPages();
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();

    // Titulo do treino
    $pdf->SetFont('Helvetica', 'B', 14);
    $pdf->SetTextColor(30, 30, 30);
    $pdf->Cell(0, 8, training_pdf_encode($pdf->trainingTitle), 0, 1, 'L');

    // Instrucoes gerais
    $instructions = trim((string) ($plan['instructions'] ?? ''));
    if ($instructions !== '') {
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->MultiCell(0, 5, training_pdf_encode($instructions));
    }

    $pdf->Ln(4);

    // Agrupar exercicios por ficha (sheet)
    $sheets = [];
    $exercises = $plan['exercises'] ?? [];
    foreach ($exercises as $ex) {
        $name = trim((string) ($ex['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $sid = trim((string) ($ex['sheet_idx'] ?? '')) ?: 'sheet1';
        if (!isset($sheets[$sid])) {
            $sheets[$sid] = [
                'title'     => trim((string) ($ex['sheet_title'] ?? 'Ficha')),
                'month'     => trim((string) ($ex['sheet_ref_month'] ?? '')),
                'year'      => trim((string) ($ex['sheet_ref_year'] ?? '')),
                'exercises' => [],
            ];
        }
        $sheets[$sid]['exercises'][] = $ex;
    }

    if (empty($sheets)) {
        $pdf->SetFont('Helvetica', 'I', 11);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->Cell(0, 10, training_pdf_encode('Nenhum exercicio cadastrado.'), 0, 1, 'C');
        return $pdf->Output('S');
    }

    // Ordenar fichas pelo titulo
    uasort($sheets, function ($a, $b) {
        return strcmp($a['title'] ?? '', $b['title'] ?? '');
    });

    // Larguras das colunas
    $colW = [8, 62, 18, 18, 22, 52]; // #, Exercicio, Series, Reps, Carga, Obs
    $tableW = array_sum($colW);

    foreach ($sheets as $sheet) {
        // Verificar espaco antes de iniciar nova ficha
        if ($pdf->GetY() > 240) {
            $pdf->AddPage();
        }

        // Titulo da ficha
        $sheetLabel = $sheet['title'] ?: 'Ficha';
        $refParts = [];
        if ($sheet['month'] !== '') {
            $refParts[] = str_pad($sheet['month'], 2, '0', STR_PAD_LEFT);
        }
        if ($sheet['year'] !== '') {
            $refParts[] = $sheet['year'];
        }
        if ($refParts) {
            $sheetLabel .= ' - Ref. ' . implode('/', $refParts);
        }

        // Barra de titulo da ficha
        $pdf->SetFillColor(255, 107, 53);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell($tableW, 8, training_pdf_encode($sheetLabel), 0, 1, 'L', true);

        // Cabecalho da tabela
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetTextColor(50, 50, 50);
        $headers = ['#', 'Exercicio', 'Series', 'Reps', 'Carga', 'Observacoes'];
        for ($i = 0; $i < count($headers); $i++) {
            $pdf->Cell($colW[$i], 7, training_pdf_encode($headers[$i]), 1, 0, 'C', true);
        }
        $pdf->Ln();

        // Linhas dos exercicios
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(30, 30, 30);
        $num = 0;
        foreach ($sheet['exercises'] as $ex) {
            $num++;
            $cues = training_pdf_parse_cues($ex['cues'] ?? null);
            $order = trim($cues['order']) ?: (string) $num;

            $exName = trim((string) ($ex['name'] ?? ''));
            $series = trim($cues['series']);
            $reps = trim($cues['reps']);
            $load = trim($cues['load']);
            $notes = trim($cues['notes']);

            // Calcular altura necessaria para a linha (baseado no texto mais longo)
            $nameLines = $pdf->GetStringWidth(training_pdf_encode($exName)) / ($colW[1] - 2);
            $notesLines = $notes !== '' ? $pdf->GetStringWidth(training_pdf_encode($notes)) / ($colW[5] - 2) : 0;
            $maxLines = max(1, ceil($nameLines), ceil($notesLines));
            $rowH = max(6, $maxLines * 4.5);

            // Quebra de pagina se necessario
            if ($pdf->GetY() + $rowH > 275) {
                $pdf->AddPage();
                // Repetir cabecalho da tabela
                $pdf->SetFillColor(255, 107, 53);
                $pdf->SetFont('Helvetica', 'B', 11);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->Cell($tableW, 8, training_pdf_encode($sheetLabel . ' (cont.)'), 0, 1, 'L', true);

                $pdf->SetFillColor(240, 240, 240);
                $pdf->SetFont('Helvetica', 'B', 8);
                $pdf->SetTextColor(50, 50, 50);
                for ($i = 0; $i < count($headers); $i++) {
                    $pdf->Cell($colW[$i], 7, training_pdf_encode($headers[$i]), 1, 0, 'C', true);
                }
                $pdf->Ln();
                $pdf->SetFont('Helvetica', '', 8);
                $pdf->SetTextColor(30, 30, 30);
            }

            // Fundo alternado
            $fill = ($num % 2 === 0);
            if ($fill) {
                $pdf->SetFillColor(250, 250, 250);
            }

            $y0 = $pdf->GetY();
            $x0 = $pdf->GetX();

            // Se a linha for simples (sem multiline), usar Cell
            if ($rowH <= 6) {
                $pdf->Cell($colW[0], $rowH, training_pdf_encode($order), 'LR', 0, 'C', $fill);
                $pdf->Cell($colW[1], $rowH, training_pdf_encode($exName), 'LR', 0, 'L', $fill);
                $pdf->Cell($colW[2], $rowH, training_pdf_encode($series), 'LR', 0, 'C', $fill);
                $pdf->Cell($colW[3], $rowH, training_pdf_encode($reps), 'LR', 0, 'C', $fill);
                $pdf->Cell($colW[4], $rowH, training_pdf_encode($load), 'LR', 0, 'C', $fill);
                $pdf->Cell($colW[5], $rowH, training_pdf_encode($notes), 'LR', 0, 'L', $fill);
                $pdf->Ln();
            } else {
                // Linha multi-line: usar abordagem com MultiCell para nome e obs
                // Desenhar fundo e bordas primeiro
                if ($fill) {
                    $pdf->Rect($x0, $y0, $tableW, $rowH, 'F');
                }

                // Coluna #
                $pdf->SetXY($x0, $y0);
                $pdf->Cell($colW[0], $rowH, training_pdf_encode($order), 'LR', 0, 'C');

                // Coluna Exercicio (multiline)
                $pdf->SetXY($x0 + $colW[0], $y0);
                $pdf->MultiCell($colW[1], 4.5, training_pdf_encode($exName), 'LR', 'L');

                // Series
                $pdf->SetXY($x0 + $colW[0] + $colW[1], $y0);
                $pdf->Cell($colW[2], $rowH, training_pdf_encode($series), 'LR', 0, 'C');

                // Reps
                $pdf->SetXY($x0 + $colW[0] + $colW[1] + $colW[2], $y0);
                $pdf->Cell($colW[3], $rowH, training_pdf_encode($reps), 'LR', 0, 'C');

                // Carga
                $pdf->SetXY($x0 + $colW[0] + $colW[1] + $colW[2] + $colW[3], $y0);
                $pdf->Cell($colW[4], $rowH, training_pdf_encode($load), 'LR', 0, 'C');

                // Observacoes (multiline)
                $pdf->SetXY($x0 + $colW[0] + $colW[1] + $colW[2] + $colW[3] + $colW[4], $y0);
                $pdf->MultiCell($colW[5], 4.5, training_pdf_encode($notes), 'LR', 'L');

                $pdf->SetY($y0 + $rowH);
            }
        }

        // Linha inferior da tabela
        $pdf->Cell($tableW, 0, '', 'T');
        $pdf->Ln(8);
    }

    return $pdf->Output('S');
}

/**
 * Salva o PDF de backup no diretorio de backups.
 *
 * @return string Caminho completo do arquivo salvo
 */
function training_pdf_save_backup(string $pdfData, string $email): string
{
    $dir = __DIR__ . '/../storage/training_backups';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $safeEmail = preg_replace('/[^a-z0-9@.\-_]/i', '_', $email);
    $filename = 'treino_' . $safeEmail . '_' . date('Y-m-d_His') . '.pdf';
    $path = $dir . '/' . $filename;

    file_put_contents($path, $pdfData);

    app_log('training_pdf_backup_saved', [
        'email'    => $email,
        'filename' => $filename,
        'size'     => strlen($pdfData),
    ]);

    return $path;
}

/**
 * Lista os backups de PDF existentes para um aluno.
 *
 * @return array Lista de arrays com 'filename', 'date', 'size'
 */
function training_pdf_list_backups(string $email): array
{
    $dir = __DIR__ . '/../storage/training_backups';
    if (!is_dir($dir)) {
        return [];
    }

    $safeEmail = preg_replace('/[^a-z0-9@.\-_]/i', '_', $email);
    $pattern = $dir . '/treino_' . $safeEmail . '_*.pdf';
    $files = glob($pattern);
    if (!$files) {
        return [];
    }

    $result = [];
    foreach ($files as $file) {
        $basename = basename($file);
        // Extrair data do nome: treino_email_YYYY-MM-DD_HHmmss.pdf
        if (preg_match('/_(\d{4}-\d{2}-\d{2}_\d{6})\.pdf$/', $basename, $m)) {
            $dateStr = str_replace('_', ' ', $m[1]);
            $dateStr = preg_replace('/(\d{2})(\d{2})(\d{2})$/', '$1:$2:$3', $dateStr);
        } else {
            $dateStr = date('Y-m-d H:i:s', filemtime($file));
        }
        $result[] = [
            'filename' => $basename,
            'date'     => $dateStr,
            'size'     => filesize($file),
        ];
    }

    // Ordenar do mais recente ao mais antigo
    usort($result, function ($a, $b) {
        return strcmp($b['filename'], $a['filename']);
    });

    return $result;
}
