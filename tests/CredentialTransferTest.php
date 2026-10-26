<?php

namespace Thevps\Vault\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Thevps\Vault\Models\Credential;
use Thevps\Vault\Models\CredentialGroup;
use Thevps\Vault\Support\CredentialCsv;
use ZipArchive;

class CredentialTransferTest extends TestCase
{
    use RefreshDatabase;

    private TestUser $owner;

    private CredentialGroup $hosting;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();

        $this->owner = TestUser::create(['name' => 'Owner']);
        $this->actingAs($this->owner);
        $this->post(route('password-groups.store'), ['name' => 'Хостинг'])->assertRedirect();
        $this->post(route('password-groups.store'), ['name' => 'Домени'])->assertRedirect();
        $this->hosting = CredentialGroup::where('name', 'Хостинг')->firstOrFail();
    }

    public function test_import_pasted_from_excel_tab_separated(): void
    {
        $text = "Назва\tЛогін\tПароль\tГрупи\n"
            ."cPanel\tadmin\ts3cret\t\n"
            ."Реєстратор\troot\t=p@ss\tхостинг, Домени\n";

        $this->post(route('passwords.transfer.import'), ['text' => $text, 'default_group_id' => $this->hosting->id])
            ->assertRedirect(route('passwords.index'))
            ->assertSessionHas('success');

        $this->assertSame(2, Credential::count());
        $cpanel = Credential::where('name', 'cPanel')->firstOrFail();
        $this->assertSame('s3cret', $cpanel->password);
        $this->assertSame([$this->hosting->id], $cpanel->groups->pluck('id')->all());
        $this->assertSame('=p@ss', Credential::where('name', 'Реєстратор')->firstOrFail()->password);
        $this->assertCount(2, Credential::where('name', 'Реєстратор')->firstOrFail()->groups);
    }

    public function test_import_csv_file_with_bom_and_semicolons(): void
    {
        $csv = CredentialCsv::header().CredentialCsv::line(['Router', 'admin', "'-x;y", 'https://192.168.1.1', '', 'note', 'Хостинг']);
        $file = UploadedFile::fake()->createWithContent('passwords.csv', $csv);

        $this->post(route('passwords.transfer.import'), ['file' => $file])->assertSessionHasNoErrors();

        $credential = Credential::firstOrFail();
        $this->assertSame('-x;y', $credential->password);
        $this->assertSame('note', $credential->notes);
    }

    public function test_import_xlsx_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('xl/sharedStrings.xml', '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<si><t>Назва</t></si><si><t>Пароль</t></si><si><t>Група</t></si><si><t>NAS</t></si><si><r><t>Хост</t></r><r><t>инг</t></r></si></sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .'<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c></row>'
            .'<row r="2"><c r="A2" t="s"><v>3</v></c><c r="B2"><v>12345</v></c><c r="C2" t="s"><v>4</v></c></row>'
            .'</sheetData></worksheet>');
        $zip->close();

        $file = new UploadedFile($path, 'passwords.xlsx', null, null, true);
        $this->post(route('passwords.transfer.import'), ['file' => $file])->assertSessionHasNoErrors();

        $credential = Credential::firstOrFail();
        $this->assertSame('NAS', $credential->name);
        $this->assertSame('12345', $credential->password);
        $this->assertSame([$this->hosting->id], $credential->groups->pluck('id')->all());
    }

    public function test_any_invalid_row_imports_nothing_and_errors_do_not_leak_values(): void
    {
        $text = "Назва;Пароль;Групи\nGood;secret-one;Хостинг\n;secret-two;Хостинг\nBad;secret-three;Чужа група\n";

        $response = $this->post(route('passwords.transfer.import'), ['text' => $text]);

        $response->assertSessionHasErrors(['row_3', 'row_4']);
        $this->assertSame(0, Credential::count());
        $messages = implode(' ', session('errors')->all());
        $this->assertStringNotContainsString('secret', $messages);
    }

    public function test_group_without_manage_right_is_rejected(): void
    {
        $viewer = TestUser::create(['name' => 'Viewer']);
        $this->post(route('password-groups.members.store', $this->hosting), ['user_id' => $viewer->id, 'access_level' => 'edit']);

        $this->actingAs($viewer)
            ->post(route('passwords.transfer.import'), ['text' => "Назва;Групи\nX;Хостинг\n"])
            ->assertSessionHasErrors('row_2');
        $this->actingAs($viewer)
            ->post(route('passwords.transfer.import'), ['text' => "Назва\nX\n", 'default_group_id' => $this->hosting->id])
            ->assertSessionHasErrors('default_group_id');
        $this->assertSame(0, Credential::count());
    }

    public function test_duplicates_are_skipped(): void
    {
        $this->post(route('passwords.store'), ['visibility' => 'group', 'name' => 'cPanel', 'login' => 'admin', 'group_ids' => [$this->hosting->id]]);

        $this->post(route('passwords.transfer.import'), [
            'text' => "Назва;Логін;Групи\ncpanel;ADMIN;Хостинг\nNew;;Хостинг\nNew;;Хостинг\n",
        ])->assertSessionHas('success', 'Імпортовано паролів: 1. Пропущено дублікатів: 2.');

        $this->assertSame(2, Credential::count());
    }

    public function test_export_contains_only_visible_credentials_and_round_trips(): void
    {
        $this->post(route('passwords.store'), ['visibility' => 'group', 'name' => 'Mine', 'password' => '=SUM(A1)', 'group_ids' => [$this->hosting->id]]);

        $stranger = TestUser::create(['name' => 'Stranger']);
        $this->actingAs($stranger)->post(route('password-groups.store'), ['name' => 'Private']);
        $private = CredentialGroup::where('name', 'Private')->firstOrFail();
        $this->post(route('passwords.store'), ['visibility' => 'group', 'name' => 'Hidden', 'password' => 'x', 'group_ids' => [$private->id]]);

        $csv = $this->actingAs($this->owner)->get(route('passwords.transfer.export'))->assertOk()->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("'=SUM(A1)", $csv);
        $this->assertStringNotContainsString('Hidden', $csv);

        Credential::query()->delete();
        $this->post(route('passwords.transfer.import'), ['text' => $csv])->assertSessionHasNoErrors();
        $this->assertSame('=SUM(A1)', Credential::where('name', 'Mine')->firstOrFail()->password);
    }

    public function test_template_and_page_render(): void
    {
        $this->get(route('passwords.transfer.template'))->assertOk()->assertDownload('passwords-template.csv');
        $this->get(route('passwords.transfer'))->assertOk();
    }
}
