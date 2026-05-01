<?php

namespace App\Services;

use TCPDF;

class PdfReport extends TCPDF
{
    private array  $company = [];
    private string $title   = '';
    private string $sub     = '';

    public function setup(array $company, string $title, string $sub = ''): static
    {
        $this->company = $company;
        $this->title   = $title;
        $this->sub     = $sub;
        return $this;
    }

    public function Header(): void
    {
        $w = $this->getPageWidth() - $this->lMargin - $this->rMargin;

        $this->SetFont('dejavusans', 'B', 14);
        $this->SetY(5);
        $this->Cell($w, 7, $this->company['company_name'] ?? 'الشركة', 0, 1, 'C');

        if (!empty($this->company['company_address'])) {
            $this->SetFont('dejavusans', '', 9);
            $this->SetTextColor(80, 80, 80);
            $this->Cell($w, 5, $this->company['company_address'], 0, 1, 'C');
            $this->SetTextColor(0, 0, 0);
        }

        if (!empty($this->company['company_phone'])) {
            $this->SetFont('dejavusans', '', 8);
            $this->SetTextColor(100, 100, 100);
            $this->Cell($w, 4, $this->company['company_phone'], 0, 1, 'C');
            $this->SetTextColor(0, 0, 0);
        }

        $this->SetFont('dejavusans', 'B', 11);
        $this->Cell($w, 7, $this->title, 0, 1, 'C');

        if ($this->sub) {
            $this->SetFont('dejavusans', '', 9);
            $this->SetTextColor(80, 80, 80);
            $this->Cell($w, 5, $this->sub, 0, 1, 'C');
            $this->SetTextColor(0, 0, 0);
        }

        $y = $this->GetY() + 1;
        $this->SetDrawColor(180, 180, 180);
        $this->SetLineWidth(0.4);
        $this->Line($this->lMargin, $y, $this->getPageWidth() - $this->rMargin, $y);
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.2);
        $this->Ln(3);
    }

    public function Footer(): void
    {
        $this->SetY(-12);
        $this->SetFont('dejavusans', '', 8);
        $this->SetTextColor(150, 150, 150);
        $w = $this->getPageWidth() - $this->lMargin - $this->rMargin;
        $this->Cell($w, 5,
            date('Y-m-d') . '   |   صفحة ' . $this->getAliasNumPage() . ' من ' . $this->getAliasNbPages(),
            0, 0, 'C');
        $this->SetTextColor(0, 0, 0);
    }

    public static function make(string $title, string $sub = ''): static
    {
        $company = \App\Models\Setting::whereIn('key', ['company_name', 'company_address', 'company_phone'])
            ->get()->pluck('value', 'key')->all();

        $pdf = new static('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('المالية');
        $pdf->SetTitle($title);
        $pdf->SetMargins(10, 48, 10);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->SetRTL(true);
        $pdf->setup($company, $title, $sub);
        $pdf->AddPage();
        $pdf->SetFont('dejavusans', '', 9);
        return $pdf;
    }

    public function respond(string $filename): \Illuminate\Http\Response
    {
        return response($this->Output('', 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }

    public static function n(float|string $v): string
    {
        return number_format((float) $v, 2, '.', ',');
    }

    /** Draw a styled table header row */
    public function tableHead(array $labels, array $widths, int $h = 7): void
    {
        $this->SetFont('dejavusans', 'B', 9);
        $this->SetFillColor(51, 65, 85);
        $this->SetTextColor(255, 255, 255);
        foreach (array_map(null, $labels, $widths) as [$label, $w]) {
            $this->Cell($w, $h, $label, 1, 0, 'C', true);
        }
        $this->Ln();
        $this->SetFillColor(255, 255, 255);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('dejavusans', '', 9);
    }

    /** Draw a section sub-header spanning the full content width */
    public function sectionHead(string $label, int $h = 6): void
    {
        $this->SetFont('dejavusans', 'B', 9);
        $this->SetFillColor(226, 232, 240);
        $this->SetTextColor(30, 41, 59);
        $w = $this->getPageWidth() - $this->lMargin - $this->rMargin;
        $this->Cell($w, $h, $label, 1, 1, 'R', true);
        $this->SetFillColor(255, 255, 255);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('dejavusans', '', 9);
    }

    /** Draw a totals row */
    public function totalsRow(array $values, array $widths, int $h = 7): void
    {
        $this->SetFont('dejavusans', 'B', 9);
        $this->SetFillColor(241, 245, 249);
        $this->SetTextColor(0, 0, 0);
        foreach (array_map(null, $values, $widths) as [$val, $w]) {
            $this->Cell($w, $h, $val, 1, 0, 'C', true);
        }
        $this->Ln();
        $this->SetFillColor(255, 255, 255);
        $this->SetFont('dejavusans', '', 9);
    }
}
