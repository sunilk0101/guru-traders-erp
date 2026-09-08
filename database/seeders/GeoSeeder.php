<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Illuminate\Database\Seeder;

/**
 * States and cities for the countries LookupSeeder creates, feeding the
 * cascading Country -> State -> City dropdowns on the Buyer master.
 *
 * Depth is deliberately uneven. India is the exporter's own country and gets
 * every state and union territory with real city coverage — the textile belt
 * (Tirupur, Coimbatore, Erode, Ludhiana, Surat) is where the jobbers are.
 * Buyer-side countries get their full first-level divisions and their main
 * commercial cities, which is what a buyer address actually needs.
 *
 * This is a starting point, not the client's data. Every row is editable once
 * the Lookups screen under Settings is built; until then a missing city needs a
 * developer, which is worth saying out loud.
 *
 * Idempotent — firstOrCreate on the natural key (country+state, state+city), so
 * re-running on a deploy neither duplicates nor overwrites an edited row.
 */
class GeoSeeder extends Seeder
{
    public function run(): void
    {
        $countries = Country::pluck('id', 'iso_code');
        $states = 0;
        $cities = 0;

        foreach ($this->data() as $iso => $rows) {
            $countryId = $countries[$iso] ?? null;

            if (! $countryId) {
                continue;
            }

            foreach ($rows as [$stateName, $stateCode, $cityNames]) {
                $state = State::firstOrCreate(
                    ['country_id' => $countryId, 'name' => $stateName],
                    ['code' => $stateCode]
                );

                $states++;

                foreach ($cityNames as $cityName) {
                    City::firstOrCreate(['state_id' => $state->id, 'name' => $cityName]);
                    $cities++;
                }
            }
        }

        $this->command?->info("Geo seeded: {$states} states, {$cities} cities.");
    }

    /**
     * @return array<string, array<int, array{0: string, 1: ?string, 2: array<int, string>}>>
     */
    private function data(): array
    {
        return [
            'IN' => $this->india(),
            'GB' => $this->unitedKingdom(),
            'US' => $this->unitedStates(),
            'AE' => $this->uae(),
            'DE' => $this->germany(),
            'FR' => $this->france(),
            'IT' => $this->italy(),
            'ES' => $this->spain(),
            'NL' => $this->netherlands(),
            'AU' => $this->australia(),
            'CA' => $this->canada(),
            'JP' => $this->japan(),
            'FJ' => $this->fiji(),
        ];
    }

    /** Fiji's four administrative divisions + main commercial cities. */
    private function fiji(): array
    {
        return [
            ['Central Division', 'C', ['Suva', 'Nasinu', 'Nausori', 'Lami']],
            ['Western Division', 'W', ['Nadi', 'Lautoka', 'Ba', 'Sigatoka']],
            ['Northern Division', 'N', ['Labasa', 'Savusavu']],
            ['Eastern Division', 'E', ['Levuka']],
        ];
    }

    /** 28 states + 8 union territories. */
    private function india(): array
    {
        return [
            ['Andhra Pradesh', 'AP', ['Visakhapatnam', 'Vijayawada', 'Guntur', 'Nellore', 'Tirupati', 'Kurnool']],
            ['Arunachal Pradesh', 'AR', ['Itanagar', 'Naharlagun']],
            ['Assam', 'AS', ['Guwahati', 'Silchar', 'Dibrugarh', 'Jorhat']],
            ['Bihar', 'BR', ['Patna', 'Gaya', 'Bhagalpur', 'Muzaffarpur']],
            ['Chhattisgarh', 'CG', ['Raipur', 'Bhilai', 'Bilaspur', 'Korba']],
            ['Goa', 'GA', ['Panaji', 'Margao', 'Vasco da Gama']],
            ['Gujarat', 'GJ', ['Ahmedabad', 'Surat', 'Vadodara', 'Rajkot', 'Bhavnagar', 'Jamnagar', 'Gandhinagar']],
            ['Haryana', 'HR', ['Gurugram', 'Faridabad', 'Panipat', 'Ambala', 'Karnal', 'Hisar']],
            ['Himachal Pradesh', 'HP', ['Shimla', 'Solan', 'Dharamshala', 'Baddi']],
            ['Jharkhand', 'JH', ['Ranchi', 'Jamshedpur', 'Dhanbad', 'Bokaro']],
            ['Karnataka', 'KA', ['Bengaluru', 'Mysuru', 'Mangaluru', 'Hubballi', 'Belagavi', 'Ballari', 'Davanagere']],
            ['Kerala', 'KL', ['Kochi', 'Thiruvananthapuram', 'Kozhikode', 'Thrissur', 'Kannur', 'Kollam']],
            ['Madhya Pradesh', 'MP', ['Indore', 'Bhopal', 'Jabalpur', 'Gwalior', 'Ujjain']],
            ['Maharashtra', 'MH', ['Mumbai', 'Pune', 'Nagpur', 'Nashik', 'Aurangabad', 'Solapur', 'Kolhapur', 'Thane']],
            ['Manipur', 'MN', ['Imphal']],
            ['Meghalaya', 'ML', ['Shillong', 'Tura']],
            ['Mizoram', 'MZ', ['Aizawl']],
            ['Nagaland', 'NL', ['Kohima', 'Dimapur']],
            ['Odisha', 'OD', ['Bhubaneswar', 'Cuttack', 'Rourkela', 'Berhampur']],
            ['Punjab', 'PB', ['Ludhiana', 'Amritsar', 'Jalandhar', 'Patiala', 'Bathinda', 'Mohali']],
            ['Rajasthan', 'RJ', ['Jaipur', 'Jodhpur', 'Udaipur', 'Kota', 'Ajmer', 'Bhilwara']],
            ['Sikkim', 'SK', ['Gangtok']],
            ['Tamil Nadu', 'TN', [
                'Chennai', 'Coimbatore', 'Tirupur', 'Madurai', 'Salem', 'Erode', 'Tiruchirappalli',
                'Tirunelveli', 'Vellore', 'Thoothukudi', 'Karur', 'Namakkal', 'Kanchipuram', 'Dindigul',
            ]],
            ['Telangana', 'TG', ['Hyderabad', 'Warangal', 'Nizamabad', 'Karimnagar']],
            ['Tripura', 'TR', ['Agartala']],
            ['Uttar Pradesh', 'UP', ['Lucknow', 'Kanpur', 'Noida', 'Ghaziabad', 'Agra', 'Varanasi', 'Meerut', 'Prayagraj']],
            ['Uttarakhand', 'UK', ['Dehradun', 'Haridwar', 'Roorkee', 'Haldwani']],
            ['West Bengal', 'WB', ['Kolkata', 'Howrah', 'Siliguri', 'Durgapur', 'Asansol']],

            // Union territories
            ['Andaman and Nicobar Islands', 'AN', ['Port Blair']],
            ['Chandigarh', 'CH', ['Chandigarh']],
            ['Dadra and Nagar Haveli and Daman and Diu', 'DH', ['Silvassa', 'Daman', 'Diu']],
            ['Delhi', 'DL', ['New Delhi', 'Delhi']],
            ['Jammu and Kashmir', 'JK', ['Srinagar', 'Jammu']],
            ['Ladakh', 'LA', ['Leh', 'Kargil']],
            ['Lakshadweep', 'LD', ['Kavaratti']],
            ['Puducherry', 'PY', ['Puducherry', 'Karaikal']],
        ];
    }

    private function unitedKingdom(): array
    {
        return [
            ['England', 'ENG', [
                'London', 'Manchester', 'Birmingham', 'Leeds', 'Liverpool', 'Bristol',
                'Sheffield', 'Nottingham', 'Leicester', 'Newcastle upon Tyne', 'Southampton',
            ]],
            ['Scotland', 'SCT', ['Glasgow', 'Edinburgh', 'Aberdeen', 'Dundee']],
            ['Wales', 'WLS', ['Cardiff', 'Swansea', 'Newport']],
            ['Northern Ireland', 'NIR', ['Belfast', 'Londonderry']],
        ];
    }

    private function unitedStates(): array
    {
        return [
            ['Alabama', 'AL', ['Birmingham', 'Montgomery', 'Mobile']],
            ['Alaska', 'AK', ['Anchorage', 'Juneau']],
            ['Arizona', 'AZ', ['Phoenix', 'Tucson', 'Mesa']],
            ['Arkansas', 'AR', ['Little Rock', 'Fayetteville']],
            ['California', 'CA', ['Los Angeles', 'San Francisco', 'San Diego', 'San Jose', 'Sacramento', 'Long Beach', 'Oakland']],
            ['Colorado', 'CO', ['Denver', 'Colorado Springs', 'Aurora']],
            ['Connecticut', 'CT', ['Bridgeport', 'Hartford', 'New Haven']],
            ['Delaware', 'DE', ['Wilmington', 'Dover']],
            ['District of Columbia', 'DC', ['Washington']],
            ['Florida', 'FL', ['Miami', 'Orlando', 'Tampa', 'Jacksonville', 'Fort Lauderdale']],
            ['Georgia', 'GA', ['Atlanta', 'Savannah', 'Augusta']],
            ['Hawaii', 'HI', ['Honolulu']],
            ['Idaho', 'ID', ['Boise']],
            ['Illinois', 'IL', ['Chicago', 'Aurora', 'Springfield']],
            ['Indiana', 'IN', ['Indianapolis', 'Fort Wayne']],
            ['Iowa', 'IA', ['Des Moines', 'Cedar Rapids']],
            ['Kansas', 'KS', ['Wichita', 'Kansas City', 'Topeka']],
            ['Kentucky', 'KY', ['Louisville', 'Lexington']],
            ['Louisiana', 'LA', ['New Orleans', 'Baton Rouge']],
            ['Maine', 'ME', ['Portland', 'Augusta']],
            ['Maryland', 'MD', ['Baltimore', 'Annapolis']],
            ['Massachusetts', 'MA', ['Boston', 'Worcester', 'Springfield']],
            ['Michigan', 'MI', ['Detroit', 'Grand Rapids', 'Lansing']],
            ['Minnesota', 'MN', ['Minneapolis', 'Saint Paul']],
            ['Mississippi', 'MS', ['Jackson', 'Gulfport']],
            ['Missouri', 'MO', ['Kansas City', 'St. Louis', 'Springfield']],
            ['Montana', 'MT', ['Billings', 'Helena']],
            ['Nebraska', 'NE', ['Omaha', 'Lincoln']],
            ['Nevada', 'NV', ['Las Vegas', 'Reno', 'Carson City']],
            ['New Hampshire', 'NH', ['Manchester', 'Concord']],
            ['New Jersey', 'NJ', ['Newark', 'Jersey City', 'Trenton']],
            ['New Mexico', 'NM', ['Albuquerque', 'Santa Fe']],
            ['New York', 'NY', ['New York City', 'Buffalo', 'Rochester', 'Albany', 'Syracuse']],
            ['North Carolina', 'NC', ['Charlotte', 'Raleigh', 'Greensboro', 'Durham']],
            ['North Dakota', 'ND', ['Fargo', 'Bismarck']],
            ['Ohio', 'OH', ['Columbus', 'Cleveland', 'Cincinnati', 'Toledo']],
            ['Oklahoma', 'OK', ['Oklahoma City', 'Tulsa']],
            ['Oregon', 'OR', ['Portland', 'Salem', 'Eugene']],
            ['Pennsylvania', 'PA', ['Philadelphia', 'Pittsburgh', 'Harrisburg', 'Allentown']],
            ['Rhode Island', 'RI', ['Providence']],
            ['South Carolina', 'SC', ['Charleston', 'Columbia', 'Greenville']],
            ['South Dakota', 'SD', ['Sioux Falls', 'Pierre']],
            ['Tennessee', 'TN', ['Nashville', 'Memphis', 'Knoxville']],
            ['Texas', 'TX', ['Houston', 'Dallas', 'Austin', 'San Antonio', 'Fort Worth', 'El Paso']],
            ['Utah', 'UT', ['Salt Lake City', 'Provo']],
            ['Vermont', 'VT', ['Burlington', 'Montpelier']],
            ['Virginia', 'VA', ['Virginia Beach', 'Richmond', 'Norfolk']],
            ['Washington', 'WA', ['Seattle', 'Spokane', 'Tacoma', 'Olympia']],
            ['West Virginia', 'WV', ['Charleston', 'Huntington']],
            ['Wisconsin', 'WI', ['Milwaukee', 'Madison']],
            ['Wyoming', 'WY', ['Cheyenne', 'Casper']],
        ];
    }

    private function uae(): array
    {
        return [
            ['Abu Dhabi', 'AZ', ['Abu Dhabi', 'Al Ain']],
            ['Dubai', 'DU', ['Dubai', 'Jebel Ali']],
            ['Sharjah', 'SH', ['Sharjah']],
            ['Ajman', 'AJ', ['Ajman']],
            ['Umm Al Quwain', 'UQ', ['Umm Al Quwain']],
            ['Ras Al Khaimah', 'RK', ['Ras Al Khaimah']],
            ['Fujairah', 'FU', ['Fujairah']],
        ];
    }

    private function germany(): array
    {
        return [
            ['Baden-Württemberg', 'BW', ['Stuttgart', 'Karlsruhe', 'Mannheim', 'Freiburg']],
            ['Bavaria', 'BY', ['Munich', 'Nuremberg', 'Augsburg']],
            ['Berlin', 'BE', ['Berlin']],
            ['Brandenburg', 'BB', ['Potsdam', 'Cottbus']],
            ['Bremen', 'HB', ['Bremen', 'Bremerhaven']],
            ['Hamburg', 'HH', ['Hamburg']],
            ['Hesse', 'HE', ['Frankfurt am Main', 'Wiesbaden', 'Kassel', 'Darmstadt']],
            ['Lower Saxony', 'NI', ['Hanover', 'Braunschweig', 'Osnabrück']],
            ['Mecklenburg-Vorpommern', 'MV', ['Rostock', 'Schwerin']],
            ['North Rhine-Westphalia', 'NW', ['Cologne', 'Düsseldorf', 'Dortmund', 'Essen', 'Duisburg', 'Bonn']],
            ['Rhineland-Palatinate', 'RP', ['Mainz', 'Ludwigshafen', 'Koblenz']],
            ['Saarland', 'SL', ['Saarbrücken']],
            ['Saxony', 'SN', ['Dresden', 'Leipzig', 'Chemnitz']],
            ['Saxony-Anhalt', 'ST', ['Magdeburg', 'Halle']],
            ['Schleswig-Holstein', 'SH', ['Kiel', 'Lübeck']],
            ['Thuringia', 'TH', ['Erfurt', 'Jena']],
        ];
    }

    private function france(): array
    {
        return [
            ['Auvergne-Rhône-Alpes', 'ARA', ['Lyon', 'Grenoble', 'Saint-Étienne']],
            ['Bourgogne-Franche-Comté', 'BFC', ['Dijon', 'Besançon']],
            ['Brittany', 'BRE', ['Rennes', 'Brest']],
            ['Centre-Val de Loire', 'CVL', ['Orléans', 'Tours']],
            ['Corsica', 'COR', ['Ajaccio', 'Bastia']],
            ['Grand Est', 'GES', ['Strasbourg', 'Reims', 'Metz']],
            ['Hauts-de-France', 'HDF', ['Lille', 'Amiens', 'Roubaix']],
            ['Île-de-France', 'IDF', ['Paris', 'Boulogne-Billancourt', 'Saint-Denis']],
            ['Normandy', 'NOR', ['Rouen', 'Le Havre', 'Caen']],
            ['Nouvelle-Aquitaine', 'NAQ', ['Bordeaux', 'Limoges', 'Poitiers']],
            ['Occitanie', 'OCC', ['Toulouse', 'Montpellier', 'Nîmes']],
            ['Pays de la Loire', 'PDL', ['Nantes', 'Angers', 'Le Mans']],
            ["Provence-Alpes-Côte d'Azur", 'PAC', ['Marseille', 'Nice', 'Toulon', 'Cannes']],
        ];
    }

    private function italy(): array
    {
        return [
            ['Abruzzo', 'ABR', ["L'Aquila", 'Pescara']],
            ['Aosta Valley', 'VDA', ['Aosta']],
            ['Apulia', 'PUG', ['Bari', 'Taranto', 'Foggia']],
            ['Basilicata', 'BAS', ['Potenza', 'Matera']],
            ['Calabria', 'CAL', ['Reggio Calabria', 'Catanzaro']],
            ['Campania', 'CAM', ['Naples', 'Salerno']],
            ['Emilia-Romagna', 'EMR', ['Bologna', 'Modena', 'Parma', 'Rimini']],
            ['Friuli-Venezia Giulia', 'FVG', ['Trieste', 'Udine']],
            ['Lazio', 'LAZ', ['Rome', 'Latina']],
            ['Liguria', 'LIG', ['Genoa', 'La Spezia']],
            ['Lombardy', 'LOM', ['Milan', 'Brescia', 'Bergamo', 'Como']],
            ['Marche', 'MAR', ['Ancona', 'Pesaro']],
            ['Molise', 'MOL', ['Campobasso']],
            ['Piedmont', 'PIE', ['Turin', 'Novara', 'Biella']],
            ['Sardinia', 'SAR', ['Cagliari', 'Sassari']],
            ['Sicily', 'SIC', ['Palermo', 'Catania', 'Messina']],
            ['Trentino-South Tyrol', 'TAA', ['Trento', 'Bolzano']],
            ['Tuscany', 'TOS', ['Florence', 'Prato', 'Pisa', 'Livorno']],
            ['Umbria', 'UMB', ['Perugia', 'Terni']],
            ['Veneto', 'VEN', ['Venice', 'Verona', 'Padua', 'Vicenza', 'Treviso']],
        ];
    }

    private function spain(): array
    {
        return [
            ['Andalusia', 'AN', ['Seville', 'Málaga', 'Córdoba', 'Granada']],
            ['Aragon', 'AR', ['Zaragoza', 'Huesca']],
            ['Asturias', 'AS', ['Oviedo', 'Gijón']],
            ['Balearic Islands', 'IB', ['Palma']],
            ['Basque Country', 'PV', ['Bilbao', 'San Sebastián', 'Vitoria-Gasteiz']],
            ['Canary Islands', 'CN', ['Las Palmas', 'Santa Cruz de Tenerife']],
            ['Cantabria', 'CB', ['Santander']],
            ['Castile and León', 'CL', ['Valladolid', 'Salamanca', 'Burgos']],
            ['Castilla-La Mancha', 'CM', ['Toledo', 'Albacete']],
            ['Catalonia', 'CT', ['Barcelona', 'Tarragona', 'Girona', 'Sabadell']],
            ['Extremadura', 'EX', ['Badajoz', 'Mérida']],
            ['Galicia', 'GA', ['Vigo', 'A Coruña', 'Santiago de Compostela']],
            ['La Rioja', 'RI', ['Logroño']],
            ['Community of Madrid', 'MD', ['Madrid', 'Móstoles', 'Alcalá de Henares']],
            ['Region of Murcia', 'MC', ['Murcia', 'Cartagena']],
            ['Navarre', 'NC', ['Pamplona']],
            ['Valencian Community', 'VC', ['Valencia', 'Alicante', 'Elche']],
        ];
    }

    private function netherlands(): array
    {
        return [
            ['Drenthe', 'DR', ['Assen', 'Emmen']],
            ['Flevoland', 'FL', ['Lelystad', 'Almere']],
            ['Friesland', 'FR', ['Leeuwarden']],
            ['Gelderland', 'GE', ['Arnhem', 'Nijmegen', 'Apeldoorn']],
            ['Groningen', 'GR', ['Groningen']],
            ['Limburg', 'LI', ['Maastricht', 'Venlo']],
            ['North Brabant', 'NB', ['Eindhoven', 'Tilburg', 'Breda', "'s-Hertogenbosch"]],
            ['North Holland', 'NH', ['Amsterdam', 'Haarlem', 'Alkmaar']],
            ['Overijssel', 'OV', ['Zwolle', 'Enschede']],
            ['South Holland', 'ZH', ['Rotterdam', 'The Hague', 'Leiden', 'Delft']],
            ['Utrecht', 'UT', ['Utrecht', 'Amersfoort']],
            ['Zeeland', 'ZE', ['Middelburg', 'Vlissingen']],
        ];
    }

    private function australia(): array
    {
        return [
            ['New South Wales', 'NSW', ['Sydney', 'Newcastle', 'Wollongong']],
            ['Victoria', 'VIC', ['Melbourne', 'Geelong', 'Ballarat']],
            ['Queensland', 'QLD', ['Brisbane', 'Gold Coast', 'Cairns', 'Townsville']],
            ['South Australia', 'SA', ['Adelaide']],
            ['Western Australia', 'WA', ['Perth', 'Fremantle']],
            ['Tasmania', 'TAS', ['Hobart', 'Launceston']],
            ['Australian Capital Territory', 'ACT', ['Canberra']],
            ['Northern Territory', 'NT', ['Darwin', 'Alice Springs']],
        ];
    }

    private function canada(): array
    {
        return [
            ['Alberta', 'AB', ['Calgary', 'Edmonton']],
            ['British Columbia', 'BC', ['Vancouver', 'Victoria', 'Surrey']],
            ['Manitoba', 'MB', ['Winnipeg']],
            ['New Brunswick', 'NB', ['Moncton', 'Saint John', 'Fredericton']],
            ['Newfoundland and Labrador', 'NL', ["St. John's"]],
            ['Nova Scotia', 'NS', ['Halifax']],
            ['Ontario', 'ON', ['Toronto', 'Ottawa', 'Mississauga', 'Hamilton', 'London']],
            ['Prince Edward Island', 'PE', ['Charlottetown']],
            ['Quebec', 'QC', ['Montreal', 'Quebec City', 'Laval', 'Gatineau']],
            ['Saskatchewan', 'SK', ['Saskatoon', 'Regina']],
            ['Northwest Territories', 'NT', ['Yellowknife']],
            ['Nunavut', 'NU', ['Iqaluit']],
            ['Yukon', 'YT', ['Whitehorse']],
        ];
    }

    /** The commercially relevant prefectures rather than all 47. */
    private function japan(): array
    {
        return [
            ['Tokyo', '13', ['Tokyo', 'Shinjuku', 'Shibuya']],
            ['Osaka', '27', ['Osaka', 'Sakai']],
            ['Kanagawa', '14', ['Yokohama', 'Kawasaki']],
            ['Aichi', '23', ['Nagoya', 'Toyota']],
            ['Hokkaido', '01', ['Sapporo', 'Hakodate']],
            ['Fukuoka', '40', ['Fukuoka', 'Kitakyushu']],
            ['Hyogo', '28', ['Kobe', 'Himeji']],
            ['Kyoto', '26', ['Kyoto']],
            ['Saitama', '11', ['Saitama', 'Kawaguchi']],
            ['Chiba', '12', ['Chiba', 'Funabashi']],
            ['Hiroshima', '34', ['Hiroshima']],
            ['Miyagi', '04', ['Sendai']],
            ['Shizuoka', '22', ['Shizuoka', 'Hamamatsu']],
            ['Niigata', '15', ['Niigata']],
            ['Okinawa', '47', ['Naha']],
        ];
    }
}
