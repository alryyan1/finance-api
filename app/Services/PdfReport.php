<?php

namespace App\Services;

use TCPDF;

/**
 * Reusable PDF report base class.
 * Inject into any report controller via PdfReport::make().
 * Supports company logo with three layout modes:
 *   - 'right'  : logo on the physical right side, text on the left
 *   - 'left'   : logo on the physical left side, text on the right
 *   - 'full'   : logo spans the full header width (banner style)
 */
class PdfReport extends TCPDF
{
    protected array  $reportCompany = [];
    protected string $reportTitle   = '';
    protected string $reportSub     = '';

    public function setup(array $company, string $title, string $sub = ''): static
    {
        $this->reportCompany = $company;
        $this->reportTitle   = $title;
        $this->reportSub     = $sub;
        return $this;
    }

    // ── Header ────────────────────────────────────────────────────────────────

    public function Header(): void
    {
        $company  = $this->reportCompany;
        $logoPath = $company['_logo_path'] ?? null;
        $logoPos  = $company['logo_position'] ?? 'right';

        $pageW    = $this->getPageWidth();
        $lM       = $this->lMargin;
        $rM       = $this->rMargin;
        $contentW = $pageW - $lM - $rM;

        if ($logoPath && $logoPos === 'full') {
            $this->headerFull($logoPath, $contentW, $lM);
        } elseif ($logoPath) {
            $this->headerSide($logoPath, $logoPos, $contentW, $lM, $rM, $pageW);
        } else {
            $this->headerTextOnly($contentW);
        }

        $this->headerTitle($contentW);
        $this->headerDivider($lM, $pageW, $rM);
    }

    /** Full-width banner logo, company info centered below */
    private function headerFull(string $logo, float $contentW, float $lM): void
    {
        $logoH = 22;
        $this->Image($logo, $lM, 5, $contentW, $logoH, '', '', '', true, 150, '', false, false, 0, 'CM');
        $this->SetY(5 + $logoH + 2);

        $c = $this->reportCompany;
        $this->SetFont('arialbd', '', 11);
        $this->Cell($contentW, 5, $c['company_name'] ?? '', 0, 1, 'C');

        if (!empty($c['company_address'])) {
            $this->SetFont('arial', '', 8);
            $this->SetTextColor(80, 80, 80);
            $this->Cell($contentW, 4, $c['company_address'], 0, 1, 'C');
            $this->SetTextColor(0, 0, 0);
        }

        if (!empty($c['company_phone'])) {
            $this->SetFont('arial', '', 8);
            $this->SetTextColor(100, 100, 100);
            $this->Cell($contentW, 4, $c['company_phone'], 0, 1, 'C');
            $this->SetTextColor(0, 0, 0);
        }
    }

    /**
     * Side logo layout.
     * In RTL documents, 'right' means the physical right side of the paper
     * (which is the natural leading edge in Arabic layout).
     *
     * Coordinate note (TCPDF RTL mode):
     *   SetX($p) → internal cursor x = pageWidth − $p
     *   Cell($w) draws from cursor x leftward by $w.
     *   To place a cell's right edge at physical position $x2:
     *     SetX( pageWidth − $x2 )
     */
    private function headerSide(
        string $logo,
        string $side,
        float  $contentW,
        float  $lM,
        float  $rM,
        float  $pageW,
    ): void {
        $c      = $this->reportCompany;
        $logoW  = 30;  // mm
        $logoH  = 20;  // mm
        $gap    = 4;   // mm between logo and text
        $textW  = $contentW - $logoW - $gap;
        $startY = 5;

        if ($side === 'right') {
            // Logo physically on the right
            $logoX     = $pageW - $rM - $logoW;
            // Text area right edge is just before the logo
            $textX2    = $pageW - $rM - $logoW - $gap;
            $setXParam = $pageW - $textX2;   // RTL SetX param
        } else {
            // Logo physically on the left
            $logoX     = $lM;
            // Text fills the rest (up to right margin)
            $textX2    = $pageW - $rM;
            $setXParam = $rM;                // RTL SetX param (default start)
        }

        // Draw logo (Image always uses absolute coords)
        $this->Image($logo, $logoX, $startY, $logoW, $logoH, '', '', '', true, 150, '', false, false, 0, 'CM');

        // Render company text in the text area
        $this->SetY($startY);

        $this->SetX($setXParam);
        $this->SetFont('arialbd', '', 13);
        $this->Cell($textW, 7, $c['company_name'] ?? 'الشركة', 0, 1, 'C');

        if (!empty($c['company_address'])) {
            $this->SetX($setXParam);
            $this->SetFont('arial', '', 9);
            $this->SetTextColor(80, 80, 80);
            $this->Cell($textW, 5, $c['company_address'], 0, 1, 'C');
            $this->SetTextColor(0, 0, 0);
        }

        if (!empty($c['company_phone'])) {
            $this->SetX($setXParam);
            $this->SetFont('arial', '', 8);
            $this->SetTextColor(100, 100, 100);
            $this->Cell($textW, 4, $c['company_phone'], 0, 1, 'C');
            $this->SetTextColor(0, 0, 0);
        }

        // Ensure Y is below the logo before rendering the title
        if ($this->GetY() < $startY + $logoH) {
            $this->SetY($startY + $logoH);
        }
    }

    /** Plain centered text header (no logo) */
    private function headerTextOnly(float $contentW): void
    {
        $c = $this->reportCompany;
        $this->SetY(5);

        $this->SetFont('arialbd', '', 14);
        $this->Cell($contentW, 7, $c['company_name'] ?? 'الشركة', 0, 1, 'C');

        if (!empty($c['company_address'])) {
            $this->SetFont('arial', '', 9);
            $this->SetTextColor(80, 80, 80);
            $this->Cell($contentW, 5, $c['company_address'], 0, 1, 'C');
            $this->SetTextColor(0, 0, 0);
        }

        if (!empty($c['company_phone'])) {
            $this->SetFont('arial', '', 8);
            $this->SetTextColor(100, 100, 100);
            $this->Cell($contentW, 4, $c['company_phone'], 0, 1, 'C');
            $this->SetTextColor(0, 0, 0);
        }
    }

    /** Report title + optional subtitle */
    private function headerTitle(float $contentW): void
    {
        $this->SetFont('arialbd', '', 11);
        $this->SetTextColor(0, 0, 0);
        $this->Cell($contentW, 7, $this->reportTitle, 0, 1, 'C');

        if ($this->reportSub) {
            $this->SetFont('arial', '', 9);
            $this->SetTextColor(80, 80, 80);
            $this->Cell($contentW, 5, $this->reportSub, 0, 1, 'C');
            $this->SetTextColor(0, 0, 0);
        }
    }

    /** Horizontal divider line at the bottom of the header */
    private function headerDivider(float $lM, float $pageW, float $rM): void
    {
        $y = $this->GetY() + 1;
        $this->SetDrawColor(180, 180, 180);
        $this->SetLineWidth(0.4);
        $this->Line($lM, $y, $pageW - $rM, $y);
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.2);
        $this->Ln(3);
    }

    // ── Footer ────────────────────────────────────────────────────────────────

    public function Footer(): void
    {
        $this->SetY(-12);
        $this->SetFont('arial', '', 8);
        $this->SetTextColor(150, 150, 150);
        $w = $this->getPageWidth() - $this->lMargin - $this->rMargin;
        $this->Cell($w, 5,
            date('Y-m-d') . '   |   صفحة ' . $this->getAliasNumPage() . ' من ' . $this->getAliasNbPages(),
            0, 0, 'C');
        $this->SetTextColor(0, 0, 0);
    }

    // ── Factory ───────────────────────────────────────────────────────────────

    /**
     * Build a configured PDF instance ready for content.
     * Reads all company settings (including logo) from the database.
     */
    public static function make(string $title, string $sub = ''): static
    {
        $keys    = ['company_name', 'company_address', 'company_phone', 'company_logo', 'logo_position'];
        $company = \App\Models\Setting::whereIn('key', $keys)
            ->get()->pluck('value', 'key')->all();

        // Resolve logo to an absolute file path for TCPDF
        $logoRel = $company['company_logo'] ?? '';
        if ($logoRel) {
            $abs = storage_path('app/public/' . $logoRel);
            $company['_logo_path'] = file_exists($abs) ? $abs : null;
        } else {
            $company['_logo_path'] = null;
        }

        // Dynamic top margin: larger when a logo is present
        $hasLogo   = $company['_logo_path'] !== null;
        $logoPos   = $company['logo_position'] ?? 'right';
        $topMargin = match (true) {
            $hasLogo && $logoPos === 'full' => 68,
            $hasLogo                        => 55,
            default                         => 48,
        };

        $pdf = new static('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('المالية');
        $pdf->SetTitle($title);
        $pdf->SetMargins(10, $topMargin, 10);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->SetRTL(true);
        $pdf->setup($company, $title, $sub);
        $pdf->AddPage();
        $pdf->SetFont('arial', '', 9);
        return $pdf;
    }

    // ── Output ────────────────────────────────────────────────────────────────

    public function respond(string $filename): \Illuminate\Http\Response
    {
        return response($this->Output('', 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }

    // ── Table helpers ─────────────────────────────────────────────────────────

    public static function n(float|string $v): string
    {
        return number_format((float) $v, 2, '.', ',');
    }

    /** Styled table header row */
    public function tableHead(array $labels, array $widths, int $h = 7): void
    {
        $this->SetFont('arialbd', '', 9);
        $this->SetFillColor(51, 65, 85);
        $this->SetTextColor(255, 255, 255);
        foreach (array_map(null, $labels, $widths) as [$label, $w]) {
            $this->Cell($w, $h, $label, 1, 0, 'C', true);
        }
        $this->Ln();
        $this->SetFillColor(255, 255, 255);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('arial', '', 9);
    }

    /** Section sub-header spanning full content width */
    public function sectionHead(string $label, int $h = 6): void
    {
        $this->SetFont('arialbd', '', 9);
        $this->SetFillColor(226, 232, 240);
        $this->SetTextColor(30, 41, 59);
        $w = $this->getPageWidth() - $this->lMargin - $this->rMargin;
        $this->Cell($w, $h, $label, 1, 1, 'R', true);
        $this->SetFillColor(255, 255, 255);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('arial', '', 9);
    }

    /** Totals row */
    public function totalsRow(array $values, array $widths, int $h = 7): void
    {
        $this->SetFont('arialbd', '', 9);
        $this->SetFillColor(241, 245, 249);
        $this->SetTextColor(0, 0, 0);
        foreach (array_map(null, $values, $widths) as [$val, $w]) {
            $this->Cell($w, $h, $val, 1, 0, 'C', true);
        }
        $this->Ln();
        $this->SetFillColor(255, 255, 255);
        $this->SetFont('arial', '', 9);
    }
}
