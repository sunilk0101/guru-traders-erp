<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Port;
use Illuminate\Database\Seeder;

/**
 * Destination / load ports for Buyer and Export Document dropdowns.
 *
 * Idempotent on UN/LOCODE-style `code`. Re-run safely after deploy.
 */
class PortSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->ports() as [$iso, $code, $name, $type]) {
            $countryId = $this->ensureCountry($iso);
            if (! $countryId) {
                continue;
            }

            Port::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name'       => $name,
                    'type'       => $type,
                    'country_id' => $countryId,
                    'status'     => 'active',
                ]
            );
        }
    }

    /**
     * Territories like New Caledonia / French Polynesia may be absent from the
     * ISO buyer list — still create them so Pacific ports can attach.
     */
    private function ensureCountry(string $iso): ?int
    {
        $iso = strtoupper($iso);
        $existing = Country::query()->where('iso_code', $iso)->value('id');
        if ($existing) {
            return (int) $existing;
        }

        $names = [
            'AS' => ['American Samoa', '+1'],
            'CK' => ['Cook Islands', '+682'],
            'GU' => ['Guam', '+1'],
            'MP' => ['Northern Mariana Islands', '+1'],
            'NC' => ['New Caledonia', '+687'],
            'NU' => ['Niue', '+683'],
            'PF' => ['French Polynesia', '+689'],
            'PN' => ['Pitcairn Islands', '+64'],
            'RE' => ['Réunion', '+262'],
            'TK' => ['Tokelau', '+690'],
            'WF' => ['Wallis and Futuna', '+681'],
        ];

        if (! isset($names[$iso])) {
            return null;
        }

        [$name, $dial] = $names[$iso];

        return (int) Country::query()->firstOrCreate(
            ['iso_code' => $iso],
            ['name' => $name, 'dial_code' => $dial, 'status' => 'active']
        )->id;
    }

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private function ports(): array
    {
        return [
            // —— Existing seed (India load + sample discharge) ——
            ['IN', 'INMAA', 'Chennai Port', 'sea'],
            ['IN', 'INNSA', 'Nhava Sheva', 'sea'],
            ['IN', 'INTUT', 'Tuticorin Port', 'sea'],
            ['IN', 'INCOK', 'Cochin Port', 'sea'],
            ['IN', 'INMAA4', 'Chennai Air Cargo', 'air'],
            ['GB', 'GBLON', 'London Port', 'sea'],
            ['GB', 'GBFXT', 'Felixstowe', 'sea'],
            ['US', 'USNYC', 'New York Port', 'sea'],
            ['US', 'USLAX', 'Los Angeles Port', 'sea'],
            ['AE', 'AEJEA', 'Jebel Ali', 'sea'],
            ['DE', 'DEHAM', 'Hamburg', 'sea'],
            ['NL', 'NLRTM', 'Rotterdam', 'sea'],

            // —— More India ——
            ['IN', 'INMUN', 'Mundra', 'sea'],
            ['IN', 'INCCU', 'Kolkata / Haldia', 'sea'],
            ['IN', 'INVTZ', 'Visakhapatnam', 'sea'],
            ['IN', 'INPAV', 'Pipavav', 'sea'],
            ['IN', 'INHZA', 'Hazira', 'sea'],
            ['IN', 'INIXE', 'Mangalore', 'sea'],
            ['IN', 'INIXY', 'Kandla', 'sea'],
            ['IN', 'INBOM', 'Mumbai Port', 'sea'],
            ['IN', 'INENR', 'Ennore / Kamarajar', 'sea'],
            ['IN', 'INIXC', 'Chandigarh Air Cargo', 'air'],
            ['IN', 'INBOM4', 'Mumbai Air Cargo', 'air'],
            ['IN', 'INDEL4', 'Delhi Air Cargo', 'air'],

            // —— Australia ——
            ['AU', 'AUSYD', 'Sydney (Port Botany)', 'sea'],
            ['AU', 'AUMEL', 'Melbourne', 'sea'],
            ['AU', 'AUBNE', 'Brisbane', 'sea'],
            ['AU', 'AUFRE', 'Fremantle', 'sea'],
            ['AU', 'AUADL', 'Adelaide', 'sea'],
            ['AU', 'AUDRW', 'Darwin', 'sea'],
            ['AU', 'AUNTL', 'Newcastle', 'sea'],
            ['AU', 'AUTSV', 'Townsville', 'sea'],
            ['AU', 'AUGEX', 'Geelong', 'sea'],
            ['AU', 'AUPHE', 'Port Hedland', 'sea'],
            ['AU', 'AUBWT', 'Burnie', 'sea'],
            ['AU', 'AUGLT', 'Gladstone', 'sea'],
            ['AU', 'AUMKY', 'Mackay', 'sea'],
            ['AU', 'AUCNS', 'Cairns', 'sea'],
            ['AU', 'AUHBA', 'Hobart', 'sea'],
            ['AU', 'AUPTL', 'Portland (VIC)', 'sea'],
            ['AU', 'AUEPR', 'Esperance', 'sea'],
            ['AU', 'AUBUY', 'Bunbury', 'sea'],
            ['AU', 'AUDEV', 'Devonport', 'sea'],
            ['AU', 'AULST', 'Launceston', 'sea'],
            ['AU', 'AUWEI', 'Weipa', 'sea'],
            ['AU', 'AUSYD4', 'Sydney Airport', 'air'],
            ['AU', 'AUMEL4', 'Melbourne Airport', 'air'],
            ['AU', 'AUBNE4', 'Brisbane Airport', 'air'],
            ['AU', 'AUPER4', 'Perth Airport', 'air'],
            ['AU', 'AUADL4', 'Adelaide Airport', 'air'],

            // —— New Zealand ——
            ['NZ', 'NZAKL', 'Auckland', 'sea'],
            ['NZ', 'NZTRG', 'Tauranga', 'sea'],
            ['NZ', 'NZWLG', 'Wellington', 'sea'],
            ['NZ', 'NZLYT', 'Lyttelton', 'sea'],
            ['NZ', 'NZNPE', 'Napier', 'sea'],
            ['NZ', 'NZNSN', 'Nelson', 'sea'],
            ['NZ', 'NZBLU', 'Bluff', 'sea'],
            ['NZ', 'NZTIU', 'Timaru', 'sea'],
            ['NZ', 'NZPOE', 'Port Chalmers', 'sea'],
            ['NZ', 'NZNPL', 'New Plymouth', 'sea'],
            ['NZ', 'NZGIS', 'Gisborne', 'sea'],
            ['NZ', 'NZWHK', 'Whangarei', 'sea'],
            ['NZ', 'NZAKL4', 'Auckland Airport', 'air'],
            ['NZ', 'NZCHC4', 'Christchurch Airport', 'air'],
            ['NZ', 'NZWLG4', 'Wellington Airport', 'air'],

            // —— Pacific islands ——
            ['FJ', 'FJSUV', 'Suva', 'sea'],
            ['FJ', 'FJLTK', 'Lautoka', 'sea'],
            ['FJ', 'FJNAN', 'Nadi', 'sea'],
            ['FJ', 'FJNAN4', 'Nadi Airport', 'air'],
            ['PG', 'PGPOM', 'Port Moresby', 'sea'],
            ['PG', 'PGLAE', 'Lae', 'sea'],
            ['PG', 'PGRAB', 'Rabaul / Kokopo', 'sea'],
            ['PG', 'PGMAG', 'Madang', 'sea'],
            ['PG', 'PGWWK', 'Wewak', 'sea'],
            ['PG', 'PGPOM4', 'Port Moresby Airport', 'air'],
            ['SB', 'SBHIR', 'Honiara', 'sea'],
            ['SB', 'SBHIR4', 'Honiara Airport', 'air'],
            ['VU', 'VUVLI', 'Port Vila', 'sea'],
            ['VU', 'VUSAN', 'Luganville (Santo)', 'sea'],
            ['VU', 'VUVLI4', 'Bauerfield Airport', 'air'],
            ['NC', 'NCNME', 'Nouméa', 'sea'],
            ['NC', 'NCNME4', 'Nouméa Airport', 'air'],
            ['PF', 'PFPPT', 'Papeete', 'sea'],
            ['PF', 'PFPPT4', 'Faa\'a Airport', 'air'],
            ['WS', 'WSAPW', 'Apia', 'sea'],
            ['WS', 'WSAPW4', 'Faleolo Airport', 'air'],
            ['TO', 'TOTBU', 'Nuku\'alofa', 'sea'],
            ['TO', 'TOTBU4', 'Fua\'amotu Airport', 'air'],
            ['CK', 'CKRAR', 'Avatiu (Rarotonga)', 'sea'],
            ['CK', 'CKRAR4', 'Rarotonga Airport', 'air'],
            ['KI', 'KITRW', 'Tarawa / Betio', 'sea'],
            ['MH', 'MHMAJ', 'Majuro', 'sea'],
            ['MH', 'MHEBE', 'Ebeye / Kwajalein', 'sea'],
            ['FM', 'FMPNI', 'Pohnpei', 'sea'],
            ['FM', 'FMTRK', 'Chuuk (Weno)', 'sea'],
            ['FM', 'FMYAP', 'Yap', 'sea'],
            ['FM', 'FMKSA', 'Kosrae', 'sea'],
            ['PW', 'PWROR', 'Koror / Malakal', 'sea'],
            ['AS', 'ASPPG', 'Pago Pago', 'sea'],
            ['GU', 'GUGUM', 'Apra Harbor (Guam)', 'sea'],
            ['GU', 'GUGUM4', 'Guam Airport', 'air'],
            ['MP', 'MPSPN', 'Saipan', 'sea'],
            ['NR', 'NRINU', 'Nauru', 'sea'],
            ['TV', 'TVFUN', 'Funafuti', 'sea'],
            ['NU', 'NUIUE', 'Alofi (Niue)', 'sea'],
            ['WF', 'WFWLS', 'Mata-Utu', 'sea'],
            ['PN', 'PNPCN', 'Adamstown (Pitcairn)', 'sea'],
            ['TK', 'TKFAL', 'Fakaofo / Nukunonu', 'sea'],

            // —— United Kingdom / Ireland ——
            ['GB', 'GBSOU', 'Southampton', 'sea'],
            ['GB', 'GBLIV', 'Liverpool', 'sea'],
            ['GB', 'GBTIL', 'Tilbury', 'sea'],
            ['GB', 'GBBRS', 'Bristol', 'sea'],
            ['GB', 'GBTEE', 'Teesport', 'sea'],
            ['GB', 'GBIMM', 'Immingham', 'sea'],
            ['GB', 'GBLHR4', 'London Heathrow', 'air'],
            ['GB', 'GBMAN4', 'Manchester Airport', 'air'],
            ['IE', 'IEDUB', 'Dublin', 'sea'],
            ['IE', 'IEORK', 'Cork', 'sea'],

            // —— Europe ——
            ['BE', 'BEANR', 'Antwerp', 'sea'],
            ['BE', 'BEZEE', 'Zeebrugge', 'sea'],
            ['FR', 'FRLEH', 'Le Havre', 'sea'],
            ['FR', 'FRMRS', 'Marseille', 'sea'],
            ['FR', 'FRDKK', 'Dunkirk', 'sea'],
            ['ES', 'ESBCN', 'Barcelona', 'sea'],
            ['ES', 'ESVLC', 'Valencia', 'sea'],
            ['ES', 'ESALG', 'Algeciras', 'sea'],
            ['PT', 'PTLIS', 'Lisbon', 'sea'],
            ['PT', 'PTSIE', 'Sines', 'sea'],
            ['IT', 'ITGOA', 'Genoa', 'sea'],
            ['IT', 'ITGIT', 'Gioia Tauro', 'sea'],
            ['IT', 'ITLIV', 'Livorno', 'sea'],
            ['IT', 'ITNAP', 'Naples', 'sea'],
            ['GR', 'GRPIR', 'Piraeus', 'sea'],
            ['GR', 'GRSKG', 'Thessaloniki', 'sea'],
            ['DE', 'DEBRV', 'Bremerhaven', 'sea'],
            ['PL', 'PLGDN', 'Gdansk', 'sea'],
            ['PL', 'PLGDY', 'Gdynia', 'sea'],
            ['SE', 'SEGOT', 'Gothenburg', 'sea'],
            ['DK', 'DKAAR', 'Aarhus', 'sea'],
            ['DK', 'DKCPH', 'Copenhagen', 'sea'],
            ['NO', 'NOOSL', 'Oslo', 'sea'],
            ['FI', 'FIHEL', 'Helsinki', 'sea'],
            ['TR', 'TRIST', 'Istanbul / Ambarli', 'sea'],
            ['TR', 'TRIZM', 'Izmir', 'sea'],
            ['TR', 'TRMER', 'Mersin', 'sea'],

            // —— Middle East ——
            ['AE', 'AEAUH', 'Abu Dhabi / Khalifa', 'sea'],
            ['AE', 'AESHJ', 'Sharjah', 'sea'],
            ['AE', 'AEDXB4', 'Dubai Airport', 'air'],
            ['SA', 'SAJED', 'Jeddah', 'sea'],
            ['SA', 'SADMM', 'Dammam', 'sea'],
            ['OM', 'OMSLL', 'Salalah', 'sea'],
            ['OM', 'OMMCT', 'Muscat / Sohar', 'sea'],
            ['QA', 'QADOH', 'Doha / Hamad', 'sea'],
            ['BH', 'BHBAH', 'Bahrain / Khalifa Bin Salman', 'sea'],
            ['KW', 'KWSWK', 'Shuwaikh', 'sea'],
            ['IL', 'ILASH', 'Ashdod', 'sea'],
            ['IL', 'ILHFA', 'Haifa', 'sea'],

            // —— East / South / SE Asia ——
            ['SG', 'SGSIN', 'Singapore', 'sea'],
            ['SG', 'SGSIN4', 'Singapore Changi', 'air'],
            ['HK', 'HKHKG', 'Hong Kong', 'sea'],
            ['HK', 'HKHKG4', 'Hong Kong Airport', 'air'],
            ['CN', 'CNSHA', 'Shanghai', 'sea'],
            ['CN', 'CNNGB', 'Ningbo', 'sea'],
            ['CN', 'CNSZX', 'Shenzhen', 'sea'],
            ['CN', 'CNTAO', 'Qingdao', 'sea'],
            ['CN', 'CNTXG', 'Tianjin / Xingang', 'sea'],
            ['CN', 'CNYTN', 'Yantian', 'sea'],
            ['CN', 'CNXMN', 'Xiamen', 'sea'],
            ['CN', 'CNCAN4', 'Guangzhou Airport', 'air'],
            ['TW', 'TWKHH', 'Kaohsiung', 'sea'],
            ['TW', 'TWKEL', 'Keelung', 'sea'],
            ['KR', 'KRPUS', 'Busan', 'sea'],
            ['KR', 'KRINC', 'Incheon', 'sea'],
            ['JP', 'JPTYO', 'Tokyo', 'sea'],
            ['JP', 'JPYOK', 'Yokohama', 'sea'],
            ['JP', 'JPOSA', 'Osaka', 'sea'],
            ['JP', 'JPUKB', 'Kobe', 'sea'],
            ['JP', 'JPNGO', 'Nagoya', 'sea'],
            ['LK', 'LKCMB', 'Colombo', 'sea'],
            ['BD', 'BDCGP', 'Chittagong', 'sea'],
            ['BD', 'BDDAC4', 'Dhaka Airport', 'air'],
            ['PK', 'PKKHI', 'Karachi', 'sea'],
            ['PK', 'PKQCT', 'Port Qasim', 'sea'],
            ['TH', 'THLCH', 'Laem Chabang', 'sea'],
            ['TH', 'THBKK', 'Bangkok', 'sea'],
            ['VN', 'VNSGN', 'Ho Chi Minh / Cat Lai', 'sea'],
            ['VN', 'VNHPH', 'Haiphong', 'sea'],
            ['VN', 'VNDAD', 'Da Nang', 'sea'],
            ['MY', 'MYPKG', 'Port Klang', 'sea'],
            ['MY', 'MYTPP', 'Tanjung Pelepas', 'sea'],
            ['MY', 'MYPEN', 'Penang', 'sea'],
            ['ID', 'IDJKT', 'Jakarta / Tanjung Priok', 'sea'],
            ['ID', 'IDSUB', 'Surabaya', 'sea'],
            ['ID', 'IDBLW', 'Belawan', 'sea'],
            ['PH', 'PHMNL', 'Manila', 'sea'],
            ['PH', 'PHCEB', 'Cebu', 'sea'],
            ['PH', 'PHDVO', 'Davao', 'sea'],
            ['MM', 'MMRGN', 'Yangon', 'sea'],
            ['KH', 'KHKOS', 'Sihanoukville', 'sea'],

            // —— Africa ——
            ['ZA', 'ZADUR', 'Durban', 'sea'],
            ['ZA', 'ZACPT', 'Cape Town', 'sea'],
            ['ZA', 'ZAPLZ', 'Port Elizabeth / Gqeberha', 'sea'],
            ['ZA', 'ZAJNB4', 'Johannesburg Airport', 'air'],
            ['NG', 'NGLOS', 'Lagos / Apapa', 'sea'],
            ['NG', 'NGTIN', 'Tin Can Island', 'sea'],
            ['KE', 'KEMBA', 'Mombasa', 'sea'],
            ['TZ', 'TZDAR', 'Dar es Salaam', 'sea'],
            ['EG', 'EGALY', 'Alexandria', 'sea'],
            ['EG', 'EGPSD', 'Port Said', 'sea'],
            ['EG', 'EGSOK', 'Sokhna', 'sea'],
            ['MA', 'MACAS', 'Casablanca', 'sea'],
            ['GH', 'GHTEM', 'Tema', 'sea'],
            ['CI', 'CIABJ', 'Abidjan', 'sea'],
            ['DJ', 'DJJIB', 'Djibouti', 'sea'],
            ['MU', 'MUPLU', 'Port Louis', 'sea'],
            ['RE', 'REPDG', 'Port Réunion', 'sea'],

            // —— Americas ——
            ['US', 'USLGB', 'Long Beach', 'sea'],
            ['US', 'USOAK', 'Oakland', 'sea'],
            ['US', 'USSEA', 'Seattle', 'sea'],
            ['US', 'USTIW', 'Tacoma', 'sea'],
            ['US', 'USHOU', 'Houston', 'sea'],
            ['US', 'USSAV', 'Savannah', 'sea'],
            ['US', 'USCHS', 'Charleston', 'sea'],
            ['US', 'USMIA', 'Miami', 'sea'],
            ['US', 'USORF', 'Norfolk', 'sea'],
            ['US', 'USBAL', 'Baltimore', 'sea'],
            ['US', 'USNYC4', 'JFK Airport', 'air'],
            ['US', 'USLAX4', 'Los Angeles Airport', 'air'],
            ['CA', 'CAVAN', 'Vancouver', 'sea'],
            ['CA', 'CAMTR', 'Montreal', 'sea'],
            ['CA', 'CAHAL', 'Halifax', 'sea'],
            ['CA', 'CAPRR', 'Prince Rupert', 'sea'],
            ['MX', 'MXZLO', 'Manzanillo', 'sea'],
            ['MX', 'MXLZC', 'Lázaro Cárdenas', 'sea'],
            ['MX', 'MXVER', 'Veracruz', 'sea'],
            ['BR', 'BRSSZ', 'Santos', 'sea'],
            ['BR', 'BRRIG', 'Rio Grande', 'sea'],
            ['BR', 'BRPNG', 'Paranaguá', 'sea'],
            ['AR', 'ARBUE', 'Buenos Aires', 'sea'],
            ['CL', 'CLVAP', 'Valparaíso', 'sea'],
            ['CL', 'CLSAI', 'San Antonio', 'sea'],
            ['PE', 'PECLL', 'Callao', 'sea'],
            ['CO', 'COCTG', 'Cartagena', 'sea'],
            ['PA', 'PACTB', 'Balboa', 'sea'],
            ['PA', 'PACOL', 'Colón / Manzanillo', 'sea'],
        ];
    }
}
