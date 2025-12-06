<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\NraGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MemberImportController extends Controller
{
    public function template(Request $request)
    {
        $filename = 'members_import_template_' . now()->format('Ymd_His') . '.xlsx';
        $zip = new \ZipArchive();
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $contentTypes = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>
XML;
        $rootRels = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML;
        $workbook = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Template" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>
XML;
        $workbookRels = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>
XML;
        $sheet = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>
    <row r="1">
      <c r="A1" t="inlineStr"><is><t>full_name</t></is></c>
      <c r="B1" t="inlineStr"><is><t>email</t></is></c>
      <c r="C1" t="inlineStr"><is><t>nip</t></is></c>
      <c r="D1" t="inlineStr"><is><t>join_date</t></is></c>
      <c r="E1" t="inlineStr"><is><t>status</t></is></c>
      <c r="F1" t="inlineStr"><is><t>phone</t></is></c>
    </row>
    <row r="2">
      <c r="A2" t="inlineStr"><is><t>Contoh Nama</t></is></c>
      <c r="B2" t="inlineStr"><is><t>contoh@example.com</t></is></c>
      <c r="C2" t="inlineStr"><is><t>NIP123</t></is></c>
      <c r="D2" t="inlineStr"><is><t>2025-01-01</t></is></c>
      <c r="E2" t="inlineStr"><is><t>aktif</t></is></c>
      <c r="F2" t="inlineStr"><is><t>08123456789</t></is></c>
    </row>
  </sheetData>
</worksheet>
XML;

        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rootRels);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();
        $data = file_get_contents($tmp);
        @unlink($tmp);
        return response($data, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"'
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Member::class);
        $user = $request->user();
        if (!$user || !$user->role || $user->role->name !== 'admin_unit') {
            abort(403);
        }
        if (!$user->organization_unit_id) {
            return back()->with('error', 'Akun admin unit belum memiliki unit organisasi');
        }
        $validated = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['csv','xls','xlsx'])) {
            return back()->with('error', 'Format file tidak didukung');
        }

        $full = $file->getRealPath();

        $success = 0; $failed = 0; $errors = [];
        if ($ext === 'csv') {
            if (($handle = fopen($full, 'r')) !== false) {
                $row = 0; $headers = [];
                while (($data = fgetcsv($handle)) !== false) {
                    if ($row === 0) { $headers = array_map('trim', $data); $row++; continue; }
                    $row++; $this->importRow($headers, $data, $user, $success, $failed, $errors, $row);
                }
                fclose($handle);
            }
        } elseif ($ext === 'xls') {
            $xml = simplexml_load_file($full);
            if ($xml) {
                $ns = $xml->getNamespaces(true);
                $sheet = $xml->Worksheet->Table ?? null;
                if (!$sheet && isset($ns['ss'])) {
                    $sheet = $xml->children($ns['ss'])->Worksheet->children($ns['ss'])->Table ?? null;
                }
                if ($sheet) {
                    $rows = $sheet->Row ?? [];
                    $headers = [];
                    $rowIndex = 0;
                    foreach ($rows as $r) {
                        $cells = [];
                        foreach ($r->Cell as $c) {
                            $cells[] = (string) ($c->Data ?? '');
                        }
                        if ($rowIndex === 0) { $headers = array_map('trim', $cells); $rowIndex++; continue; }
                        $rowIndex++;
                        $this->importRow($headers, $cells, $user, $success, $failed, $errors, $rowIndex);
                    }
                }
            }
        } else { // xlsx
            $zip = new \ZipArchive();
            if ($zip->open($full) === true) {
                $wb = $zip->getFromName('xl/workbook.xml');
                $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
                $sheetPath = 'xl/worksheets/sheet1.xml';
                if ($wb && $rels) {
                    // find first sheet target
                    libxml_use_internal_errors(true);
                    $xmlRels = @simplexml_load_string($rels);
                    $nsR = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
                    if ($xmlRels) {
                        foreach ($xmlRels->Relationship as $rel) {
                            $type = (string) $rel['Type'];
                            if (strpos($type, '/worksheet') !== false) { $sheetPath = 'xl/' . (string) $rel['Target']; break; }
                        }
                    }
                }
                $sheetXml = $zip->getFromName($sheetPath);
                $shared = $zip->getFromName('xl/sharedStrings.xml');
                $sharedStrings = [];
                if ($shared) {
                    libxml_use_internal_errors(true);
                    $sx = @simplexml_load_string($shared);
                    if ($sx) {
                        foreach ($sx->si as $si) {
                            $t = (string) $si->t;
                            $sharedStrings[] = $t;
                        }
                    }
                }
                if ($sheetXml) {
                    libxml_use_internal_errors(true);
                    $sx = @simplexml_load_string($sheetXml);
                    $headers = [];
                    $rowIndex = 0;
                    if ($sx) {
                        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
                        $ws = $sx->children($ns);
                        if (isset($ws->sheetData)) {
                            foreach ($ws->sheetData->children($ns)->row as $r) {
                                $cells = [];
                                foreach ($r->children($ns) as $c) {
                                    if ($c->getName() !== 'c') continue;
                                    $t = (string) $c['t'];
                                    $v = '';
                                    $cns = $c->children($ns);
                                    if ($t === 's') {
                                        $idx = (int) ($cns->v ?? 0);
                                        $v = $sharedStrings[$idx] ?? '';
                                    } elseif (isset($cns->is)) {
                                        $isns = $cns->is->children($ns);
                                        if (isset($isns->t)) { $v = (string) $isns->t; }
                                    } elseif (isset($cns->v)) {
                                        $v = (string) $cns->v;
                                    }
                                    $cells[] = $v;
                                }
                                if ($rowIndex === 0) { $headers = array_map('trim', $cells); $rowIndex++; continue; }
                                $rowIndex++;
                                $this->importRow($headers, $cells, $user, $success, $failed, $errors, $rowIndex);
                            }
                        }
                    }
                    // Fallback parser using regex for very simple XLSX
                    if (empty($headers) && $sheetXml) {
                        $headers = [];
                        $rowIndex = 0;
                        if (preg_match_all('/<row[^>]*>(.*?)<\/row>/s', $sheetXml, $rowMatches)) {
                            foreach ($rowMatches[1] as $rowStr) {
                                $cells = [];
                                if (preg_match_all('/<c[^>]*>(.*?)<\/c>/s', $rowStr, $cellMatches)) {
                                    foreach ($cellMatches[1] as $cellStr) {
                                        $val = '';
                                        if (preg_match('/<is>\s*<t>(.*?)<\/t>\s*<\/is>/s', $cellStr, $m)) { $val = html_entity_decode($m[1]); }
                                        elseif (preg_match('/<v>(.*?)<\/v>/s', $cellStr, $m)) { $val = html_entity_decode($m[1]); }
                                        $cells[] = $val;
                                    }
                                }
                                if ($rowIndex === 0) { $headers = array_map('trim', $cells); $rowIndex++; continue; }
                                $rowIndex++;
                                $this->importRow($headers, $cells, $user, $success, $failed, $errors, $rowIndex);
                            }
                        }
                    }
                }
                $zip->close();
            }
        }

        session()->flash('import_errors', $errors);
        session()->flash('import_summary', [ 'success' => $success, 'failed' => $failed ]);

        return redirect()->route('admin.members.index')->with('success', "Import selesai: sukses {$success}, gagal {$failed}");
    }

    private function importRow(array $headers, array $data, $user, int &$success, int &$failed, array &$errors, int $row)
    {
        $item = [];
        foreach ($headers as $i => $h) { $item[$h] = $data[$i] ?? null; }
        $fullName = trim($item['full_name'] ?? '');
        $email = trim($item['email'] ?? '');
        $nip = trim($item['nip'] ?? '');
        $joinDate = trim($item['join_date'] ?? '');
        $status = trim($item['status'] ?? 'aktif');
        $phone = trim($item['phone'] ?? '');
        if ($fullName === '' || $email === '') { $failed++; $errors[] = ['row'=>$row,'message'=>'full_name dan email wajib']; return; }
        try {
            $joinYear = $joinDate ? (int) date('Y', strtotime($joinDate)) : (int) now()->year;
            $gen = NraGenerator::generate((int)$user->organization_unit_id, $joinYear);
            $kta = \App\Services\KtaGenerator::generate((int)$user->organization_unit_id, $joinYear);
            Member::create([
                'full_name' => $fullName,
                'email' => $email,
                'phone' => $phone ?: null,
                'nip' => $nip ?: null,
                'employment_type' => 'organik',
                'status' => in_array($status, ['aktif','cuti','suspended','resign','pensiun']) ? $status : 'aktif',
                'join_date' => $joinDate ?: now()->toDateString(),
                'organization_unit_id' => (int) $user->organization_unit_id,
                'union_position_id' => null,
                'nra' => $gen['nra'],
                'join_year' => $joinYear,
                'sequence_number' => $gen['sequence'],
                'kta_number' => $kta['kta'],
            ]);
            $success++;
        } catch (\Throwable $e) {
            $failed++;
            $errors[] = ['row'=>$row,'message'=>$e->getMessage()];
        }
    }
}
