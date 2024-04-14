<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;


use PhpOffice\PhpSpreadsheet\IOFactory;
use PDO;

class DashboardController extends BaseController
{
    private $field;
    private $harvest;
    private $planting;
    private $worker;
    private $variety;
    private $fertilizers;
    private $equipment;
    private $prof;
    private $users;
    private $expense;
    private $profiles;
    private $damages;
    private $admin;


    public function __construct()
    {
        $this->users = new \App\Models\RegisterModel();
        $this->field = new \App\Models\VIewFieldsModel();
        $this->expense = new \App\Models\ExpensesModel();
        $this->harvest = new \App\Models\HarvestModel();
        $this->planting = new \App\Models\PlantingModel();
        $this->damages = new \App\Models\DamageModel();
        $this->worker = new \App\Models\WorkerModel();
        $this->variety = new \App\Models\VarietyModel();
        $this->fertilizers = new \App\Models\FertilizersModel();
        $this->equipment = new \App\Models\EquipmentModel();
        $this->prof = new \App\Models\FarmelProfileModel();
        $this->profiles = new \App\Models\FarmerProfilesModel();
        $this->admin = new \App\Models\AdminModel();
    }

    public function dashboards()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/logins');
        }

        $userId = session()->get('leader_id');

        // Fetch total harvest quantity
        $resultQuantity = $this->harvest
            ->selectSum('harvest_quantity', 'totalHarvestQuantity')
            ->where('user_id', $userId)
            ->get();
        $totalHarvestQuantity = $resultQuantity->getRow()->totalHarvestQuantity;

        // Fetch total revenue for the current year
        $currentYear = date('Y');
        $resultRevenue = $this->harvest
            ->selectSum('total_revenue', 'totalRevenueThisYear')
            ->where('user_id', $userId)
            ->where('YEAR(harvest_date)', $currentYear)
            ->get();
        $totalRevenueThisYear = $resultRevenue->getRow()->totalRevenueThisYear;

        // Fetch total money spent from jobs table
        $resultMoneySpent = $this->expense
            ->selectSum('total_money_spent', 'totalMoneySpent')
            ->where('user_id', $userId)
            ->get();
        $totalMoneySpent = $resultMoneySpent->getRow()->totalMoneySpent;

        /// Fetch monthly harvest quantity data
        $monthlyHarvest = $this->harvest
            ->select('YEAR(harvest_date) as year, MONTH(harvest_date) as month, SUM(harvest_quantity) as totalHarvestQuantity')
            ->where('user_id', $userId)
            ->groupBy('YEAR(harvest_date), MONTH(harvest_date)')
            ->findAll();

        // Extracting labels and data for the chart
        $monthlyLabels = array_map(function ($item) {
            return date('F Y', strtotime($item['year'] . '-' . $item['month'] . '-01'));
        }, $monthlyHarvest);

        $monthlyHarvestData = array_column($monthlyHarvest, 'totalHarvestQuantity');


        // Fetch total land area of the barangay
        $totalLandArea = $this->field
            ->selectSum('field_total_area', 'totalLandArea')
            ->get()
            ->getRow()
            ->totalLandArea;

        // Fetch total number of farmers
        $totalNoofFarmers = $this->profiles
            ->where('user_id', $userId)
            ->countAllResults();

        $harvestData = $this->harvest->where('user_id', $userId)->findAll();
        $revenueData = $this->harvest->where('user_id', $userId)->findAll();

        $data = [
            'totalHarvestQuantity' => $totalHarvestQuantity,
            'totalRevenueThisYear' => $totalRevenueThisYear,
            'harvest' => $harvestData,
            'monthlyLabels' => $monthlyLabels,
            'monthlyHarvestData' => $monthlyHarvestData,
            'totalLandArea' => $totalLandArea,
            'totalNoofFarmers' => $totalNoofFarmers,
        ];

        return view('userfolder/dashboard', $data);
    }

    public function searchProfiles()
    {
        $searchTerm = $this->request->getPost('search');

        $profiles = $this->profiles->select('fullname, fims_code')
            ->like('fullname', $searchTerm)
            ->findAll();

        $responseData = [];
        foreach ($profiles as $profile) {
            $responseData[] = [
                'fullname' => $profile['fullname'],
                'fims_code' => $profile['fims_code']
            ];
        }

        return $this->response->setJSON($responseData);
    }

    public function viewfields()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/sign_ins');
        }
        $userId = session()->get('leader_id');
        $profile = $this->profiles->where('user_id', $userId)->findAll();
        $fie = $this->field->where('user_id', $userId)->findAll();

        $data = [
            'profiles' => $profile,
            'field' => $fie,
        ];
        return view('userfolder/viewfields', $data);
    }
    public function addnewfield()
    {
        $userId = session()->get('leader_id');

        $validation = $this->validate([
            'farmer_name' => 'required',
            'field_name' => 'required',
            'field_address' => 'required',
            'field_total_area' => 'required',
        ]);

        if (!$validation) {
            return view('userfolder/viewfields', ['validation' => $this->validator]);
        }

        $selectedFarmerName = $this->request->getPost('farmer_name');
        $fimsCode = $this->profiles->where('fullname', $selectedFarmerName)->first()['fims_code'];

        $this->field->save([
            'farmer_name' => $selectedFarmerName,
            'fims_code' => $fimsCode,
            'field_name' => $this->request->getPost('field_name'),
            'field_owner' => $this->request->getPost('field_owner'),
            'field_address' => $this->request->getPost('field_address'),
            'field_total_area' => $this->request->getPost('field_total_area'),
            'user_id' => $userId,
        ]);

        return redirect()->to('/viewfields')->with('success', 'Field added successfully');
    }

    public function edit($field_id)
    {

        $field = $this->field->find($field_id);

        return view('field', ['field' => $field]);
    }
    public function update()
    {


        $field_id = $this->request->getPost('field_id');

        $dataToUpdate = [
            'farmer_name' => $this->request->getPost('farmer_name'),
            'field_name' => $this->request->getPost('field_name'),
            'field_owner' => $this->request->getPost('field_owner'),
            'field_address' => $this->request->getPost('field_address'),
            'field_total_area' => $this->request->getPost('field_total_area'),
        ];

        $this->field->update($field_id, $dataToUpdate);

        return redirect()->to('/viewfields')->with('success', 'Field updated successfully');
    }
    public function deleteProduct($field_id)
    {
        $field = $this->field->find($field_id);

        if ($field) {
            $this->field->delete($field_id);

            return redirect()->to('/viewfields')->with('success', 'field deleted successfully');
        } else {
            return redirect()->to('/viewfields')->with('error', 'field not found');
        }
    }

    //crop planting
    public function cropplanting()
    {
        $userId = session()->get('leader_id');
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/sign_ins');
        } else {
            $data = [
                'planting' => $this->planting->where('user_id', $userId)->findAll()
            ];
            return view('userfolder/cropplanting', $data);
        }
    }
    public function addnewplanting()
    {
        $userId = session()->get('leader_id');
        $fieldId = $this->request->getPost('field_id');
        $field = $this->field->find($fieldId);
        $validation = $this->validate([
            'field_name' => 'required',
            'crop_variety' => 'required',
        ]);

        if (!$validation) {
            return view('userfolder/viewfields', ['validation' => $this->validator]);
        }

        $this->planting->save([
            'field_id' => $this->request->getPost('field_id'),
            'field_name' => $this->request->getPost('field_name'),
            'crop_variety' => $this->request->getPost('crop_variety'),
            'planting_date' => $this->request->getPost('planting_date'),
            'season' => $this->request->getPost('season'),
            'start_date' => $this->request->getPost('start_date'),
            'notes' => $this->request->getPost('notes'),
            'user_id' => $userId,
            'farmer_name' => $field['farmer_name'],
            'fims_code' => $field['fims_code'],
            'field_address' => $field['field_address'],
        ]);

        return redirect()->to('/cropplanting')->with('success', 'Field added successfully');
    }

    public function editplanting($planting_id)
    {
        $planting = $this->planting->find($planting_id);

        return view('planting', ['planting' => $planting]);
    }
    public function updateplanting()
    {

        $planting_id = $this->request->getPost('planting_id');

        $dataToUpdate = [
            'farmer_name' => $this->request->getPost('farmer_name'),
            'field_name' => $this->request->getPost('field_name'),
            'crop_variety' => $this->request->getPost('crop_variety'),
            'planting_date' => $this->request->getPost('planting_date'),
            'season' => $this->request->getPost('season'),
            'start_date' => $this->request->getPost('start_date'),
            'notes' => $this->request->getPost('notes'),
        ];

        $this->planting->update($planting_id, $dataToUpdate);

        return redirect()->to('/cropplanting')->with('success', 'Field updated successfully');
    }
    public function deleteplanting($planting_id)
    {

        $planting = $this->planting->find($planting_id);

        if ($planting) {
            $this->planting->delete($planting_id);
            return redirect()->to('/cropplanting')->with('success', 'field deleted successfully');
        } else {
            return redirect()->to('/cropplanting')->with('error', 'field not found');
        }
    }

    //expense

    public function expenses()
    {
        $userId = session()->get('leader_id');
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/sign_ins');
        } else {
            $data = [
                'expense' => $this->expense->where('user_id', $userId)->findAll()
            ];
            return view('userfolder/jobs', $data);
        }
    }
    public function addnewjob()
    {

        $userId = session()->get('leader_id');
        $fieldId = $this->request->getPost('field_id');
        $field = $this->field->find($fieldId);

        $validation = $this->validate([
            'expense_name' => 'required',
            'finished_date' => 'required',
            'total_money_spent' => 'required',
            'notes' => 'required',
        ]);

        if (!$validation) {
            return view('userfolder/jobs', ['validation' => $this->validator]);
        }

        $this->expense->save([
            'field_id' => $this->request->getPost('field_id'),
            'field_name' => $this->request->getPost('field_name'),
            'expense_name' => $this->request->getPost('expense_name'),
            'finished_date' => $this->request->getPost('finished_date'),
            'total_money_spent' => $this->request->getPost('total_money_spent'),
            'notes' => $this->request->getPost('notes'),
            'user_id' => $userId,
            'farmer_name' => $field['farmer_name'],
            'fims_code' => $field['fims_code'],

        ]);

        return redirect()->to('/expenses')->with('success', 'Job added successfully');
    }


    public function editjob($job_id)
    {;
        $jobs = $this->expense->find($job_id);

        return view('jobs', ['jobs' => $jobs]);
    }
    public function updatejob()
    {


        $job_id = $this->request->getPost('job_id');

        $dataToUpdate = [
            'job_name' => $this->request->getPost('job_name'),
            'field_name' => $this->request->getPost('field_name'),
            'finished_date' => $this->request->getPost('finished_date'),
            'worker_name' => $this->request->getPost('worker_name'),
            'total_money_spent' => $this->request->getPost('total_money_spent'),
            'notes' => $this->request->getPost('notes'),
        ];

        $this->expense->update($job_id, $dataToUpdate);

        return redirect()->to('/jobs')->with('success', 'Job updated successfully');
    }
    public function deleteJob($job_id)
    {


        $jobs = $this->expense->find($job_id);

        if ($jobs) {
            $this->expense->delete($job_id);
            return redirect()->to('/jobs')->with('success', 'jobs deleted successfully');
        } else {
            return redirect()->to('/jobs')->with('error', 'jobs not found');
        }
    }

    //harvest

    public function harvest()
    {
        $userId = session()->get('leader_id');
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/sign_ins');
        }
        $data = [
            'harvest' => $this->harvest->where('user_id', $userId)->findAll()
        ];
        return view('userfolder/harvest', $data);
    }
    public function addnewharvest()
    {
        $userId = session()->get('leader_id');
        $fieldId = $this->request->getPost('field_id');
        $field = $this->field->find($fieldId);

        $validation = $this->validate([
            'field_name' => 'required',
            'variety_name' => 'required',
            'harvest_quantity' => 'required',
            'total_revenue' => 'required',
            'harvest_date' => 'required',


        ]);

        if (!$validation) {
            return view('userfolder/harvest', ['validation' => $this->validator]);
        }

        $this->harvest->save([
            'field_id' => $this->request->getPost('field_id'),
            'field_name' => $this->request->getPost('field_name'),
            'variety_name' => $this->request->getPost('variety_name'),
            'harvest_quantity' => $this->request->getPost('harvest_quantity'),
            'total_revenue' => $this->request->getPost('total_revenue'),
            'harvest_date' => $this->request->getPost('harvest_date'),
            'notes' => $this->request->getPost('notes'),
            'user_id' => $userId,
            'farmer_name' => $field['farmer_name'],
            'fims_code' => $field['fims_code'],

        ]);

        return redirect()->to('/harvest')->with('success', 'Harvest added successfully');
    }


    public function editharvest($harvest_id)
    {
        $harvest = $this->harvest->find($harvest_id);

        return view('harvest', ['harvest' => $harvest]);
    }
    public function updateharvest()
    {

        $harvest_id = $this->request->getPost('harvest_id');

        $dataToUpdate = [
            'field_name' => $this->request->getPost('field_name'),
            'variety_name' => $this->request->getPost('variety_name'),
            'harvest_quantity' => $this->request->getPost('harvest_quantity'),
            'total_revenue' => $this->request->getPost('total_revenue'),
            'harvest_date' => $this->request->getPost('harvest_date'),
            'notes' => $this->request->getPost('notes'),
        ];

        $this->harvest->update($harvest_id, $dataToUpdate);

        return redirect()->to('/harvest')->with('success', 'Harvest updated successfully');
    }
    public function deleteHarvest($harvest_id)
    {


        $jobs = $this->harvest->find($harvest_id);

        if ($jobs) {
            $this->harvest->delete($harvest_id);

            return redirect()->to('/harvest')->with('success', 'Harvest deleted successfully');
        } else {
            return redirect()->to('/harvest')->with('error', 'harvest not found');
        }
    }

    // damages
    public function damages()
    {
        $userId = session()->get('leader_id');
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/sign_ins');
        }
        $data = [
            'damages' => $this->damages->where('user_id', $userId)->findAll()
        ];
        return view('userfolder/damages', $data);
    }
    public function addnewdamage()
    {
        $userId = session()->get('leader_id');
        $fieldId = $this->request->getPost('field_id');
        $fieldAddress = $this->request->getPost('field_address');
        $fieldName = $this->request->getPost('field_name');
        $cropVariety = $this->request->getPost('crop_variety');
        $farmerName = $this->request->getPost('farmer_name');
        $fimsCode = $this->request->getPost('fims_code');
        $planting = $this->planting->find($fieldId);
        $validation = $this->validate([
            'field_name' => 'required',
        ]);

        if (!$validation) {
            return view('userfolder/damage', ['validation' => $this->validator]);
        }

        $this->damages->save([
            'field_id' => $fieldId,
            'field_address' => $fieldAddress,
            'field_name' => $fieldName,
            'crop_variety' => $cropVariety,
            'damage_type' => $this->request->getPost('damage_type'),
            'pest_type' => $this->request->getPost('pest_type'),
            'severity' => $this->request->getPost('severity'),
            'symptoms' => $this->request->getPost('symptoms'),
            'actions' => $this->request->getPost('actions'),
            'weather_events' => $this->request->getPost('weather_events'),
            'damage_descriptions' => $this->request->getPost('damage_descriptions'),
            'damage_severity' => $this->request->getPost('damage_severity'),
            'mitigation_measures' => $this->request->getPost('mitigation_measures'),
            'user_id' => $userId,
            'farmer_name' => $farmerName,
            'fims_code' => $fimsCode,
        ]);

        return redirect()->to('/damages')->with('success', 'Harvest added successfully');
    }
    public function editdamage($damage_id)
    {
        $damages = $this->damages->find($damage_id);

        return view('damages', ['damages' => $damages]);
    }
    public function updatedamage()
    {

        $damage_id = $this->request->getPost('damage_id');

        $dataToUpdate = [
            'pest_type' => $this->request->getPost('pest_type'),
            'severity' => $this->request->getPost('severity'),
            'symptoms' => $this->request->getPost('symptoms'),
            'actions' => $this->request->getPost('actions'),
            'weather_events' => $this->request->getPost('weather_events'),
            'damage_descriptions' => $this->request->getPost('damage_descriptions'),
            'damage_severity' => $this->request->getPost('damage_severity'),
            'mitigation_measures' => $this->request->getPost('mitigation_measures'),
        ];

        $this->damages->update($damage_id, $dataToUpdate);

        return redirect()->to('/damages')->with('success', 'Harvest updated successfully');
    }
    public function deletedamage($damage_id)
    {


        $damage = $this->damages->find($damage_id);

        if ($damage) {
            $this->harvest->delete($damage_id);

            return redirect()->to('/damages')->with('success', 'Harvest deleted successfully');
        } else {
            return redirect()->to('/damages')->with('error', 'harvest not found');
        }
    }


    // farmer profiles

    public function farmerprofiles()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/sign_ins');
        }
        $userId = session()->get('leader_id');
        $profile = $this->profiles->where('user_id', $userId)->findAll();

        $data = [
            'profiles' => $profile,
        ];
        return view('userfolder/farmerprofile', $data);
    }
    public function addfarmerprofile()
    {
        $userId = session()->get('leader_id');

        $validation = $this->validate([
            'fims_code' => 'required',
            'fullname' => 'required',
            'address' => 'required',
        ]);

        if (!$validation) {
            return view('userfolder/farmerprofile', ['validation' => $this->validator]);
        }


        $this->profiles->save([
            'fims_code' => $this->request->getPost('fims_code'),
            'fullname' => $this->request->getPost('fullname'),
            'address' => $this->request->getPost('address'),
            'user_id' => $userId,
        ]);


        return redirect()->to('/farmerprofiles')->with('success', 'Profile added successfully');
    }
    public function editfarmer($id)
    {
        $profile = $this->profiles->find($id);

        return view('farmerprofiles', ['profiles' => $profile]);
    }
    public function updatefarmer()
    {
        $id = $this->request->getPost('id');

        $dataToUpdate = [
            'fims_code' => $this->request->getPost('fims_code'),
            'fullname' => $this->request->getPost('fullname'),
            'address' => $this->request->getPost('address'),
        ];

        $this->profiles->update($id, $dataToUpdate);

        return redirect()->to('/farmerprofiles')->with('success', 'Profile updated successfully');
    }
    public function deletefarmer($id)
    {
        $profile = $this->profiles->find($id);

        if ($profile) {
            $this->profiles->delete($id);

            return redirect()->to('/farmerprofiles')->with('success', 'Harvest deleted successfully');
        } else {
            return redirect()->to('/farmerprofiles')->with('error', 'harvest not found');
        }
    }
    public function myprofile()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/signinadmin');
        }
        $userId = session()->get('farmer_id');
        $prof = $this->prof->where('user_id', $userId)->findAll();


        $data = [
            'prof' => $prof
        ];
        return view('userfolder/myprofile', $data);
    }
    public function addleaderprofile()
    {
        $userId = session()->get('farmer_id');

        $validation = $this->validate([
            'fullname' => 'required',
            'idnumber' => 'required',
            'address' => 'required',
            'contactnumber' => 'required',
            'birthday' => 'required',
            'profile_picture' => 'uploaded[profile_picture]|max_size[profile_picture,1024]|is_image[profile_picture]',
        ]);

        if (!$validation) {
            return view('userfolder/addprofile', ['validation' => $this->validator]);
        }

        $profilePicture = $this->request->getFile('profile_picture');
        $newName = $profilePicture->getRandomName();
        $profilePicture->move(ROOTPATH . 'public/uploads/profile_pictures/', $newName);

        $this->prof->save([
            'user_id' => $userId,
            'fullname' => $this->request->getPost('fullname'),
            'idnumber' => $this->request->getPost('idnumber'),
            'address' => $this->request->getPost('address'),
            'contactnumber' => $this->request->getPost('contactnumber'),
            'birthday' => $this->request->getPost('birthday'),
            'profile_picture' => 'uploads/profile_pictures/' . $newName,
        ]);

        $prof = $this->prof->where('user_id', $userId)->findAll();

        $this->prof = $prof;
        $session = session();
        $session->set('prof', $prof);

        return redirect()->to('/addprofile')->with('success', 'Profile added successfully');
    }



    public function map()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/signinadmin');
        }

        $barangays = ['Santiago', 'Kalinisan',  'Mabini', 'Adrialuna', 'Antipolo', 'Apitong', 'Arangin', 'Aurora', 'Bacungan', 'Bagong Buhay', 'Bancuro', 'Barcenaga', 'Bayani', 'Buhangin', 'Concepcion', 'Dao', 'Del Pilar', 'Estrella', 'Evangelista', 'Gamao', 'General Esco', 'Herrera', 'Inarawan', 'Laguna', 'Mabini', 'Andres Ilagan', 'Mahabang Parang', 'Malaya', 'Malinao', 'Malvar', 'Masagana', 'Masaguing', 'Melgar A', 'Melgar B', 'Metolza', 'Montelago', 'Montemayor', 'Motoderazo', 'Mulawin', 'Nag-Iba I', 'Nag-Iba II', 'Pagkakaisa', 'Paniquian', 'Pinagsabangan I', 'Pinagsabangan II', 'Pinahan', 'Poblacion I (Barangay I)', 'Poblacion II (Barangay II)', 'Poblacion III (Barangay III)', 'Sampaguita', 'San Agustin I', 'San Agustin II', 'San Andres', 'San Antonio', 'San Carlos', 'San Isidro', 'San Jose', 'San Luis', 'San Nicolas', 'San Pedro', 'Santa Isabel', 'Santa Maria', 'Santiago', 'Santo Nino', 'Tagumpay', 'Tigkan', 'Melgar B', 'Santa Cruz', 'Balite', 'Banuton', 'Caburo', 'Magtibay', 'Paitan'];
        $varietyData = [];

        foreach ($barangays as $barangay) {
            $varietyData[$barangay] = $this->planting
                ->select('crop_variety')
                ->where('field_address', $barangay)
                ->findAll();
        }

        return view('adminfolder/map', ['varietyData' => $varietyData]);
    }
    public function farmermap()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/sign_ins');
        }

        $barangays = ['Santiago', 'Kalinisan',  'Mabini', 'Adrialuna', 'Antipolo', 'Apitong', 'Arangin', 'Aurora', 'Bacungan', 'Bagong Buhay', 'Bancuro', 'Barcenaga', 'Bayani', 'Buhangin', 'Concepcion', 'Dao', 'Del Pilar', 'Estrella', 'Evangelista', 'Gamao', 'General Esco', 'Herrera', 'Inarawan', 'Laguna', 'Mabini', 'Andres Ilagan', 'Mahabang Parang', 'Malaya', 'Malinao', 'Malvar', 'Masagana', 'Masaguing', 'Melgar A', 'Melgar B', 'Metolza', 'Montelago', 'Montemayor', 'Motoderazo', 'Mulawin', 'Nag-Iba I', 'Nag-Iba II', 'Pagkakaisa', 'Paniquian', 'Pinagsabangan I', 'Pinagsabangan II', 'Pinahan', 'Poblacion I (Barangay I)', 'Poblacion II (Barangay II)', 'Poblacion III (Barangay III)', 'Sampaguita', 'San Agustin I', 'San Agustin II', 'San Andres', 'San Antonio', 'San Carlos', 'San Isidro', 'San Jose', 'San Luis', 'San Nicolas', 'San Pedro', 'Santa Isabel', 'Santa Maria', 'Santiago', 'Santo Nino', 'Tagumpay', 'Tigkan', 'Melgar B', 'Santa Cruz', 'Balite', 'Banuton', 'Caburo', 'Magtibay', 'Paitan'];
        $varietyData = [];

        foreach ($barangays as $barangay) {
            $varietyData[$barangay] = $this->planting
                ->select('crop_variety')
                ->where('field_address', $barangay)
                ->findAll();
        }
        return view('userfolder/map', ['varietyData' => $varietyData]);
    }


    public function adminfields()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/signinadmin');
        }

        $data = [
            'field' => $this->field->findAll()
        ];
        return view('adminfolder/fields', $data);
    }
    public function admincropplanting()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/signinadmin');
        }

        $data = [
            'planting' => $this->planting->findAll()
        ];
        return view('adminfolder/croprotation', $data);
    }
    public function adminexpense()
    {
        $userId = session()->get('leader_id');
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/sign_ins');
        } else {
            $data = [
                'expense' => $this->expense->findAll()
            ];
            return view('adminfolder/jobs', $data);
        }
    }
    public function adminharvest()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/signinadmin');
        }

        $data = [
            'harvest' => $this->harvest->findAll()
        ];
        return view('adminfolder/harvest', $data);
    }
    public function admindamages()
    {
        $userId = session()->get('leader_id');
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/sign_ins');
        }
        $data = [
            'damages' => $this->damages->findAll()
        ];
        return view('adminfolder/damage', $data);
    }

    public function searchFields()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/sign_ins');
        }

        $userId = session()->get('leader_id');
        $searchTerm = $this->request->getPost('search_term');

        $fields = $this->field->like('farmer_name', $searchTerm)
            ->where('user_id', $userId)
            ->findAll();
        $profiles = [];
        foreach ($fields as $field) {
            $profile = $this->profiles->where('fims_code', $field['fims_code'])->first();
            if ($profile) {
                $profiles[] = $profile;
            }
        }

        $data = [
            'field' => $fields,
            'profiles' => $profiles,
        ];

        return view('userfolder/viewfields', $data);
    }
    public function searchCropplanting()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/sign_ins');
        }

        $userId = session()->get('leader_id');
        $searchTerm = $this->request->getPost('search_term');

        $plant = $this->planting->like('farmer_name', $searchTerm)
            ->where('user_id', $userId)
            ->findAll();

        $data = [
            'planting' => $plant,
        ];

        return view('userfolder/cropplanting', $data);
    }
    public function searchExpense()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/sign_ins');
        }

        $userId = session()->get('leader_id');
        $searchTerm = $this->request->getPost('search_term');

        $exp = $this->expense->like('expense_name', $searchTerm)
            ->where('user_id', $userId)
            ->findAll();

        $data = [
            'expense' => $exp,
        ];

        return view('userfolder/jobs', $data);
    }
    public function searchHarvest()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/sign_ins');
        }

        $userId = session()->get('leader_id');
        $searchTerm = $this->request->getPost('search_term');

        $har = $this->harvest->like('farmer_name', $searchTerm)
            ->where('user_id', $userId)
            ->findAll();

        $data = [
            'harvest' => $har,
        ];

        return view('userfolder/harvest', $data);
    }


    public function searchDamage()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/sign_ins');
        }

        $userId = session()->get('leader_id');
        $searchTerm = $this->request->getPost('search_term');

        $dam = $this->damages->like('farmer_name', $searchTerm)
            ->where('user_id', $userId)
            ->findAll();

        $data = [
            'damages' => $dam,
        ];

        return view('userfolder/cropplanting', $data);
    }
    public function searchfarmerprofiles()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/sign_ins');
        }

        $userId = session()->get('leader_id');
        $searchTerm = $this->request->getPost('search_term');

        $profiles = $this->profiles->like('fullname', $searchTerm)
            ->where('user_id', $userId)
            ->findAll();

        $data = [
            'profiles' => $profiles,
        ];

        return view('userfolder/farmerprofile', $data);
    }
    public function searchadminFields()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/signinadmin');
        }

        $searchTerm = $this->request->getPost('search_term');

        $fields = $this->field->like('farmer_name', $searchTerm)
            ->findAll();
        $profiles = [];
        foreach ($fields as $field) {
            $profile = $this->profiles->where('fims_code', $field['fims_code'])->first();
            if ($profile) {
                $profiles[] = $profile;
            }
        }

        $data = [
            'field' => $fields,
            'profiles' => $profiles,
        ];

        return view('adminfolder/fields', $data);
    }
    public function searchadminCropplanting()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/signinadmin');
        }

        $searchTerm = $this->request->getPost('search_term');

        $plant = $this->planting->like('farmer_name', $searchTerm)
            ->findAll();

        $data = [
            'planting' => $plant,
        ];

        return view('adminfolder/croprotation', $data);
    }
    public function searchadminExpense()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/signinadmin');
        }

        $searchTerm = $this->request->getPost('search_term');

        $exp = $this->expense->like('expense_name', $searchTerm)
            ->findAll();

        $data = [
            'expense' => $exp,
        ];

        return view('adminfolder/jobs', $data);
    }

    public function searchadminDamage()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/signinadmin');
        }

        $searchTerm = $this->request->getPost('search_term');

        $dam = $this->damages->like('farmer_name', $searchTerm)
            ->findAll();

        $data = [
            'damages' => $dam,
        ];

        return view('adminfolder/croprotation', $data);
    }

    public function searchadminHarvest()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/signinadmin');
        }

        $searchTerm = $this->request->getPost('search_term');

        $har = $this->harvest->like('farmer_name', $searchTerm)
            ->findAll();

        $data = [
            'harvest' => $har,
        ];

        return view('adminfolder/harvest', $data);
    }
    public function searchadminmanageaccounts()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/signinadmin');
        }

        $searchTerm = $this->request->getPost('search_term');

        $adm = $this->users->like('leader_name', $searchTerm)
            ->findAll();

        $data = [
            'users' => $adm,
        ];

        return view('adminfolder/manageaccounts', $data);
    }

    public function exportToExcel()
    {
        $userId = session()->get('leader_id');
        $fields = $this->field->where('user_id', $userId)->findAll();

        $spreadsheet = new Spreadsheet();

        $spreadsheet->getActiveSheet()->setTitle('Field Data');

        $spreadsheet->getActiveSheet()->setCellValue('A1', 'Farmer Name');
        $spreadsheet->getActiveSheet()->setCellValue('B1', 'Field Name');
        $spreadsheet->getActiveSheet()->setCellValue('C1', 'Field Owner');
        $spreadsheet->getActiveSheet()->setCellValue('D1', 'Field Address');
        $spreadsheet->getActiveSheet()->setCellValue('E1', 'Field Total Area');

        $row = 2;
        foreach ($fields as $field) {
            $spreadsheet->getActiveSheet()->setCellValue('A' . $row, $field['farmer_name']);
            $spreadsheet->getActiveSheet()->setCellValue('B' . $row, $field['field_name']);
            $spreadsheet->getActiveSheet()->setCellValue('C' . $row, $field['field_owner']);
            $spreadsheet->getActiveSheet()->setCellValue('D' . $row, $field['field_address']);
            $spreadsheet->getActiveSheet()->setCellValue('E' . $row, $field['field_total_area']);
            $row++;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="field_data.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);

        $writer->save('php://output');
    }

    public function exportToExceldamage()
    {
        $userId = session()->get('leader_id');
        $damage = $this->damages->where('user_id', $userId)->findAll();

        $spreadsheet = new Spreadsheet();

        $spreadsheet->getActiveSheet()->setTitle('Damage Details');

        $spreadsheet->getActiveSheet()->setCellValue('A1', 'Field Name');
        $spreadsheet->getActiveSheet()->setCellValue('B1', 'Field Address');
        $spreadsheet->getActiveSheet()->setCellValue('C1', 'Farmer Name');
        $spreadsheet->getActiveSheet()->setCellValue('D1', 'FIMS Code');
        $spreadsheet->getActiveSheet()->setCellValue('E1', 'Crop Variety');
        $spreadsheet->getActiveSheet()->setCellValue('F1', 'Damage Type');
        $spreadsheet->getActiveSheet()->setCellValue('G1', 'Pest Type');
        $spreadsheet->getActiveSheet()->setCellValue('H1', 'Severity');
        $spreadsheet->getActiveSheet()->setCellValue('I1', 'Symptoms');
        $spreadsheet->getActiveSheet()->setCellValue('J1', 'Actions');
        $spreadsheet->getActiveSheet()->setCellValue('K1', 'Weather Events');
        $spreadsheet->getActiveSheet()->setCellValue('L1', 'Damage Descriptions');
        $spreadsheet->getActiveSheet()->setCellValue('M1', 'Damage Severity');
        $spreadsheet->getActiveSheet()->setCellValue('N1', 'Mitigation Measures');

        // Populate data
        $row = 2;
        foreach ($damage as $damages) {
            $spreadsheet->getActiveSheet()->setCellValue('A' . $row, $damages['field_name']);
            $spreadsheet->getActiveSheet()->setCellValue('B' . $row, $damages['field_address']);
            $spreadsheet->getActiveSheet()->setCellValue('C' . $row, $damages['farmer_name']);
            $spreadsheet->getActiveSheet()->setCellValue('D' . $row, $damages['fims_code']);
            $spreadsheet->getActiveSheet()->setCellValue('E' . $row, $damages['crop_variety']);
            $spreadsheet->getActiveSheet()->setCellValue('F' . $row, $damages['damage_type']);
            $spreadsheet->getActiveSheet()->setCellValue('G' . $row, $damages['pest_type']);
            $spreadsheet->getActiveSheet()->setCellValue('H' . $row, $damages['severity']);
            $spreadsheet->getActiveSheet()->setCellValue('I' . $row, $damages['symptoms']);
            $spreadsheet->getActiveSheet()->setCellValue('J' . $row, $damages['actions']);
            $spreadsheet->getActiveSheet()->setCellValue('K' . $row, $damages['weather_events']);
            $spreadsheet->getActiveSheet()->setCellValue('L' . $row, $damages['damage_descriptions']);
            $spreadsheet->getActiveSheet()->setCellValue('M' . $row, $damages['damage_severity']);
            $spreadsheet->getActiveSheet()->setCellValue('N' . $row, $damages['mitigation_measures']);
            $row++;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="damage_data.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);

        $writer->save('php://output');
    }
    public function exportToExcelplanting()
    {
        $userId = session()->get('leader_id');
        $plant = $this->planting->where('user_id', $userId)->findAll();

        $spreadsheet = new Spreadsheet();

        $spreadsheet->getActiveSheet()->setTitle('Planting Details');

        $spreadsheet->getActiveSheet()->setCellValue('A1', 'Field Address');
        $spreadsheet->getActiveSheet()->setCellValue('B1', 'Field Name');
        $spreadsheet->getActiveSheet()->setCellValue('C1', 'Crop Variety');
        $spreadsheet->getActiveSheet()->setCellValue('D1', 'Planting Date');
        $spreadsheet->getActiveSheet()->setCellValue('E1', 'Season');
        $spreadsheet->getActiveSheet()->setCellValue('F1', 'Start Date');
        $spreadsheet->getActiveSheet()->setCellValue('G1', 'Notes');
        $spreadsheet->getActiveSheet()->setCellValue('H1', 'Farmer Name');
        $spreadsheet->getActiveSheet()->setCellValue('I1', 'FIMS Code');

        // Populate data
        $row = 2;
        foreach ($plant as $planting) {
            $spreadsheet->getActiveSheet()->setCellValue('A' . $row, $planting['field_address']);
            $spreadsheet->getActiveSheet()->setCellValue('B' . $row, $planting['field_name']);
            $spreadsheet->getActiveSheet()->setCellValue('C' . $row, $planting['crop_variety']);
            $spreadsheet->getActiveSheet()->setCellValue('D' . $row, $planting['planting_date']);
            $spreadsheet->getActiveSheet()->setCellValue('E' . $row, $planting['season']);
            $spreadsheet->getActiveSheet()->setCellValue('F' . $row, $planting['start_date']);
            $spreadsheet->getActiveSheet()->setCellValue('G' . $row, $planting['notes']);
            $spreadsheet->getActiveSheet()->setCellValue('H' . $row, $planting['farmer_name']);
            $spreadsheet->getActiveSheet()->setCellValue('I' . $row, $planting['fims_code']);
            $row++;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="planting_data.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);

        $writer->save('php://output');
    }
    public function exportToExcelharvest()
    {
        $userId = session()->get('leader_id');
        $harv = $this->harvest->where('user_id', $userId)->findAll();

        $spreadsheet = new Spreadsheet();

        $spreadsheet->getActiveSheet()->setTitle('Harvest Data');

        $spreadsheet->getActiveSheet()->setCellValue('A1', 'Field Name');
        $spreadsheet->getActiveSheet()->setCellValue('B1', 'Variety Name');
        $spreadsheet->getActiveSheet()->setCellValue('C1', 'Harvest Quantity');
        $spreadsheet->getActiveSheet()->setCellValue('D1', 'Total Revenue');
        $spreadsheet->getActiveSheet()->setCellValue('E1', 'Harvest Date');
        $spreadsheet->getActiveSheet()->setCellValue('F1', 'Farmer Name');
        $spreadsheet->getActiveSheet()->setCellValue('G1', 'FIMS Code');

        // Populate data
        $row = 2;
        foreach ($harv as $harvest) {
            $spreadsheet->getActiveSheet()->setCellValue('A' . $row, $harvest['field_name']);
            $spreadsheet->getActiveSheet()->setCellValue('B' . $row, $harvest['variety_name']);
            $spreadsheet->getActiveSheet()->setCellValue('C' . $row, $harvest['harvest_quantity']);
            $spreadsheet->getActiveSheet()->setCellValue('D' . $row, $harvest['total_revenue']);
            $spreadsheet->getActiveSheet()->setCellValue('E' . $row, $harvest['harvest_date']);
            $spreadsheet->getActiveSheet()->setCellValue('F' . $row, $harvest['farmer_name']);
            $spreadsheet->getActiveSheet()->setCellValue('G' . $row, $harvest['fims_code']);
            $row++;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="harvest_data.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);

        $writer->save('php://output');
    }
    public function exportToExcelexpense()
    {
        $userId = session()->get('leader_id');
        $exp = $this->expense->where('user_id', $userId)->findAll();

        $spreadsheet = new Spreadsheet();

        $spreadsheet->getActiveSheet()->setTitle('expenses Data');

        $spreadsheet->getActiveSheet()->setCellValue('A1', 'Expense Name');
        $spreadsheet->getActiveSheet()->setCellValue('B1', 'Field Name');
        $spreadsheet->getActiveSheet()->setCellValue('C1', 'Finished Date');
        $spreadsheet->getActiveSheet()->setCellValue('D1', 'Total Money Spent');
        $spreadsheet->getActiveSheet()->setCellValue('E1', 'Notes');
        $spreadsheet->getActiveSheet()->setCellValue('F1', 'Farmer Name');
        $spreadsheet->getActiveSheet()->setCellValue('G1', 'FIMS Code');

        $row = 2;
        foreach ($exp as $expense) {
            $spreadsheet->getActiveSheet()->setCellValue('A' . $row, $expense['expense_name']);
            $spreadsheet->getActiveSheet()->setCellValue('B' . $row, $expense['field_name']);
            $spreadsheet->getActiveSheet()->setCellValue('C' . $row, $expense['finished_date']);
            $spreadsheet->getActiveSheet()->setCellValue('D' . $row, $expense['total_money_spent']);
            $spreadsheet->getActiveSheet()->setCellValue('E' . $row, $expense['notes']);
            $spreadsheet->getActiveSheet()->setCellValue('F' . $row, $expense['farmer_name']);
            $spreadsheet->getActiveSheet()->setCellValue('G' . $row, $expense['fims_code']);
            $row++;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="expenses_data.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);

        $writer->save('php://output');
    }
    public function exportToExcelfarmerprofiles()
    {
        $userId = session()->get('leader_id');
        $profiles = $this->profiles->where('user_id', $userId)->findAll();

        $spreadsheet = new Spreadsheet();

        $spreadsheet->getActiveSheet()->setTitle('profiles Details');

        $spreadsheet->getActiveSheet()->setCellValue('A1', 'FIMS Code');
        $spreadsheet->getActiveSheet()->setCellValue('B1', 'Name');
        $spreadsheet->getActiveSheet()->setCellValue('C1', 'Address');

        // Populate data
        $row = 2;
        foreach ($profiles as $profiles) {
            $spreadsheet->getActiveSheet()->setCellValue('A' . $row, $profiles['fims_code']);
            $spreadsheet->getActiveSheet()->setCellValue('B' . $row, $profiles['fullname']);
            $spreadsheet->getActiveSheet()->setCellValue('C' . $row, $profiles['address']);
            $row++;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="farmer_profiles.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);

        $writer->save('php://output');
    }

    public function exportToExceladminfields()
    {
        $userId = session()->get('leader_id');
        $fields = $this->field->findAll();

        $spreadsheet = new Spreadsheet();

        $spreadsheet->getActiveSheet()->setTitle('Field Data');

        $spreadsheet->getActiveSheet()->setCellValue('A1', 'Farmer Name');
        $spreadsheet->getActiveSheet()->setCellValue('B1', 'Field Name');
        $spreadsheet->getActiveSheet()->setCellValue('C1', 'Field Owner');
        $spreadsheet->getActiveSheet()->setCellValue('D1', 'Field Address');
        $spreadsheet->getActiveSheet()->setCellValue('E1', 'Field Total Area');

        // Populate data
        $row = 2;
        foreach ($fields as $field) {
            $spreadsheet->getActiveSheet()->setCellValue('A' . $row, $field['farmer_name']);
            $spreadsheet->getActiveSheet()->setCellValue('B' . $row, $field['field_name']);
            $spreadsheet->getActiveSheet()->setCellValue('C' . $row, $field['field_owner']);
            $spreadsheet->getActiveSheet()->setCellValue('D' . $row, $field['field_address']);
            $spreadsheet->getActiveSheet()->setCellValue('E' . $row, $field['field_total_area']);
            $row++;
        }

        // Set headers for download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="field_data.xlsx"');
        header('Cache-Control: max-age=0');

        // Create Excel writer object
        $writer = new Xlsx($spreadsheet);

        // Save Excel file to php://output (download)
        $writer->save('php://output');
    }
    public function exportToExceladminplanting()
    {
        $userId = session()->get('leader_id');
        $plant = $this->planting->findAll();

        $spreadsheet = new Spreadsheet();

        $spreadsheet->getActiveSheet()->setTitle('Planting Details');

        $spreadsheet->getActiveSheet()->setCellValue('A1', 'Field Address');
        $spreadsheet->getActiveSheet()->setCellValue('B1', 'Field Name');
        $spreadsheet->getActiveSheet()->setCellValue('C1', 'Crop Variety');
        $spreadsheet->getActiveSheet()->setCellValue('D1', 'Planting Date');
        $spreadsheet->getActiveSheet()->setCellValue('E1', 'Season');
        $spreadsheet->getActiveSheet()->setCellValue('F1', 'Start Date');
        $spreadsheet->getActiveSheet()->setCellValue('G1', 'Notes');
        $spreadsheet->getActiveSheet()->setCellValue('H1', 'Farmer Name');
        $spreadsheet->getActiveSheet()->setCellValue('I1', 'FIMS Code');

        // Populate data
        $row = 2;
        foreach ($plant as $planting) {
            $spreadsheet->getActiveSheet()->setCellValue('A' . $row, $planting['field_address']);
            $spreadsheet->getActiveSheet()->setCellValue('B' . $row, $planting['field_name']);
            $spreadsheet->getActiveSheet()->setCellValue('C' . $row, $planting['crop_variety']);
            $spreadsheet->getActiveSheet()->setCellValue('D' . $row, $planting['planting_date']);
            $spreadsheet->getActiveSheet()->setCellValue('E' . $row, $planting['season']);
            $spreadsheet->getActiveSheet()->setCellValue('F' . $row, $planting['start_date']);
            $spreadsheet->getActiveSheet()->setCellValue('G' . $row, $planting['notes']);
            $spreadsheet->getActiveSheet()->setCellValue('H' . $row, $planting['farmer_name']);
            $spreadsheet->getActiveSheet()->setCellValue('I' . $row, $planting['fims_code']);
            $row++;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="planting_data.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);

        $writer->save('php://output');
    }
    public function exportToExceladminexpense()
    {
        $exp = $this->expense->findAll();

        $spreadsheet = new Spreadsheet();

        $spreadsheet->getActiveSheet()->setTitle('expenses Data');

        $spreadsheet->getActiveSheet()->setCellValue('A1', 'Expense Name');
        $spreadsheet->getActiveSheet()->setCellValue('B1', 'Field Name');
        $spreadsheet->getActiveSheet()->setCellValue('C1', 'Finished Date');
        $spreadsheet->getActiveSheet()->setCellValue('D1', 'Total Money Spent');
        $spreadsheet->getActiveSheet()->setCellValue('E1', 'Notes');
        $spreadsheet->getActiveSheet()->setCellValue('F1', 'Farmer Name');
        $spreadsheet->getActiveSheet()->setCellValue('G1', 'FIMS Code');

        $row = 2;
        foreach ($exp as $expense) {
            $spreadsheet->getActiveSheet()->setCellValue('A' . $row, $expense['expense_name']);
            $spreadsheet->getActiveSheet()->setCellValue('B' . $row, $expense['field_name']);
            $spreadsheet->getActiveSheet()->setCellValue('C' . $row, $expense['finished_date']);
            $spreadsheet->getActiveSheet()->setCellValue('D' . $row, $expense['total_money_spent']);
            $spreadsheet->getActiveSheet()->setCellValue('E' . $row, $expense['notes']);
            $spreadsheet->getActiveSheet()->setCellValue('F' . $row, $expense['farmer_name']);
            $spreadsheet->getActiveSheet()->setCellValue('G' . $row, $expense['fims_code']);
            $row++;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="expenses_data.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);

        $writer->save('php://output');
    }


    public function exportToExceladmindamage()
    {
        $damage = $this->damages->findAll();

        $spreadsheet = new Spreadsheet();

        $spreadsheet->getActiveSheet()->setTitle('Damage Details');

        $spreadsheet->getActiveSheet()->setCellValue('A1', 'Field Name');
        $spreadsheet->getActiveSheet()->setCellValue('B1', 'Field Address');
        $spreadsheet->getActiveSheet()->setCellValue('C1', 'Farmer Name');
        $spreadsheet->getActiveSheet()->setCellValue('D1', 'FIMS Code');
        $spreadsheet->getActiveSheet()->setCellValue('E1', 'Crop Variety');
        $spreadsheet->getActiveSheet()->setCellValue('F1', 'Damage Type');
        $spreadsheet->getActiveSheet()->setCellValue('G1', 'Pest Type');
        $spreadsheet->getActiveSheet()->setCellValue('H1', 'Severity');
        $spreadsheet->getActiveSheet()->setCellValue('I1', 'Symptoms');
        $spreadsheet->getActiveSheet()->setCellValue('J1', 'Actions');
        $spreadsheet->getActiveSheet()->setCellValue('K1', 'Weather Events');
        $spreadsheet->getActiveSheet()->setCellValue('L1', 'Damage Descriptions');
        $spreadsheet->getActiveSheet()->setCellValue('M1', 'Damage Severity');
        $spreadsheet->getActiveSheet()->setCellValue('N1', 'Mitigation Measures');

        // Populate data
        $row = 2;
        foreach ($damage as $damages) {
            $spreadsheet->getActiveSheet()->setCellValue('A' . $row, $damages['field_name']);
            $spreadsheet->getActiveSheet()->setCellValue('B' . $row, $damages['field_address']);
            $spreadsheet->getActiveSheet()->setCellValue('C' . $row, $damages['farmer_name']);
            $spreadsheet->getActiveSheet()->setCellValue('D' . $row, $damages['fims_code']);
            $spreadsheet->getActiveSheet()->setCellValue('E' . $row, $damages['crop_variety']);
            $spreadsheet->getActiveSheet()->setCellValue('F' . $row, $damages['damage_type']);
            $spreadsheet->getActiveSheet()->setCellValue('G' . $row, $damages['pest_type']);
            $spreadsheet->getActiveSheet()->setCellValue('H' . $row, $damages['severity']);
            $spreadsheet->getActiveSheet()->setCellValue('I' . $row, $damages['symptoms']);
            $spreadsheet->getActiveSheet()->setCellValue('J' . $row, $damages['actions']);
            $spreadsheet->getActiveSheet()->setCellValue('K' . $row, $damages['weather_events']);
            $spreadsheet->getActiveSheet()->setCellValue('L' . $row, $damages['damage_descriptions']);
            $spreadsheet->getActiveSheet()->setCellValue('M' . $row, $damages['damage_severity']);
            $spreadsheet->getActiveSheet()->setCellValue('N' . $row, $damages['mitigation_measures']);
            $row++;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="damage_data.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);

        $writer->save('php://output');
    }

    public function exportToExceladminharvest()
    {
        $harv = $this->harvest->findAll();

        $spreadsheet = new Spreadsheet();

        $spreadsheet->getActiveSheet()->setTitle('Harvest Data');

        $spreadsheet->getActiveSheet()->setCellValue('A1', 'Field Name');
        $spreadsheet->getActiveSheet()->setCellValue('B1', 'Variety Name');
        $spreadsheet->getActiveSheet()->setCellValue('C1', 'Harvest Quantity');
        $spreadsheet->getActiveSheet()->setCellValue('D1', 'Total Revenue');
        $spreadsheet->getActiveSheet()->setCellValue('E1', 'Harvest Date');
        $spreadsheet->getActiveSheet()->setCellValue('F1', 'Farmer Name');
        $spreadsheet->getActiveSheet()->setCellValue('G1', 'FIMS Code');

        $row = 2;
        foreach ($harv as $harvest) {
            $spreadsheet->getActiveSheet()->setCellValue('A' . $row, $harvest['field_name']);
            $spreadsheet->getActiveSheet()->setCellValue('B' . $row, $harvest['variety_name']);
            $spreadsheet->getActiveSheet()->setCellValue('C' . $row, $harvest['harvest_quantity']);
            $spreadsheet->getActiveSheet()->setCellValue('D' . $row, $harvest['total_revenue']);
            $spreadsheet->getActiveSheet()->setCellValue('E' . $row, $harvest['harvest_date']);
            $spreadsheet->getActiveSheet()->setCellValue('F' . $row, $harvest['farmer_name']);
            $spreadsheet->getActiveSheet()->setCellValue('G' . $row, $harvest['fims_code']);
            $row++;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="harvest_data.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);

        $writer->save('php://output');
    }


    // charts

    public function charts()
    {
        $userId = session()->get('leader_id');
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/sign_ins');
        }

        $topBarangays = $this->field
            ->select('field_address, SUM(field_total_area) as total_area')
            ->groupBy('field_address')
            ->orderBy('total_area', 'DESC')
            ->limit(10)
            ->findAll();

        $barangayNames = array_column($topBarangays, 'field_address');
        $totalAreas = array_column($topBarangays, 'total_area');


        $chartData = [
            'labels' => $barangayNames,
            'datasets' => [
                [
                    'label' => 'Total Field Area',
                    'backgroundColor' => ['rgb(250, 208, 92)', 'rgb(136, 196, 49)'],
                    'borderWidth' => 1,
                    'data' => $totalAreas,
                ],
            ],
        ];
        $cropVarietyCount = $this->planting
            ->select('crop_variety, COUNT(crop_variety) as variety_count')
            ->where('user_id', $userId)
            ->groupBy('crop_variety')
            ->orderBy('variety_count', 'DESC')
            ->limit(10)
            ->findAll();

        $varietyNames = array_column($cropVarietyCount, 'crop_variety');
        $varietyCounts = array_column($cropVarietyCount, 'variety_count');

        $chartData2 = [
            'labels' => $varietyNames,
            'datasets' => [
                [
                    'label' => 'Number of Crop Varieties',
                    'backgroundColor' => 'rgb(250, 208, 92)',
                    'borderColor' => 'rgb(250, 208, 92)',
                    'borderWidth' => 1,
                    'data' => $varietyCounts,
                ],
            ],
        ];

        $damageCounts = $this->damages
            ->select('pest_type, COUNT(*) as count')
            ->where('user_id', $userId)
            ->groupBy('pest_type')
            ->findAll();

        $pestTypes = array_column($damageCounts, 'pest_type');
        $damageCountsData = array_column($damageCounts, 'count');

        $chartData3 = [
            'labels' => $pestTypes,
            'datasets' => [
                [
                    'label' => 'Number of Damages',
                    'backgroundColor' => 'rgb(250, 108, 92)',
                    'borderColor' => 'rgb(250, 108, 92)',
                    'borderWidth' => 1,
                    'data' => $damageCountsData,
                ],
            ],
        ];
        $weatherEventCounts = $this->damages
            ->select('weather_events, COUNT(*) as count')
            ->where('user_id', $userId)
            ->groupBy('weather_events')
            ->findAll();

        $weatherEvents = array_column($weatherEventCounts, 'weather_events');
        $weatherEventCountsData = array_column($weatherEventCounts, 'count');

        $chartData4 = [
            'labels' => $weatherEvents,
            'datasets' => [
                [
                    'label' => 'Number of Damages',
                    'backgroundColor' => 'rgb(92, 182, 250)',
                    'borderColor' => 'rgb(92, 182, 250)',
                    'borderWidth' => 1,
                    'data' => $weatherEventCountsData,
                ],
            ],
        ];
        $harvestData = $this->harvest
            ->select('harvest_date, harvest_quantity')
            ->where('user_id', $userId)
            ->findAll();

        $harvestDates = array_column($harvestData, 'harvest_date');
        $harvestQuantities = array_column($harvestData, 'harvest_quantity');

        $chartData6 = [
            'labels' => $harvestDates,
            'datasets' => [
                [
                    'label' => 'Harvest Quantity',
                    'backgroundColor' => 'rgb(54, 162, 235)',
                    'borderColor' => 'rgb(54, 162, 235)',
                    'data' => $harvestQuantities,
                ],
            ],
        ];
        $data = [
            'chartData' => $chartData,
            'chartData2' => $chartData2,
            'chartData3' => $chartData3,
            'chartData4' => $chartData4,
            'chartData6' => $chartData6,
        ];
        return view('userfolder/charts', $data);
    }
    public function admincharts()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/sign_ins');
        }

        $topBarangays = $this->field
            ->select('field_address, SUM(field_total_area) as total_area')
            ->groupBy('field_address')
            ->orderBy('total_area', 'DESC')
            ->limit(10)
            ->findAll();

        $barangayNames = array_column($topBarangays, 'field_address');
        $totalAreas = array_column($topBarangays, 'total_area');


        $chartData = [
            'labels' => $barangayNames,
            'datasets' => [
                [
                    'label' => 'Total Field Area',
                    'backgroundColor' => ['rgb(250, 208, 92)', 'rgb(136, 196, 49)'],
                    'borderWidth' => 1,
                    'data' => $totalAreas,
                ],
            ],
        ];
        $cropVarietyCount = $this->planting
            ->select('crop_variety, COUNT(crop_variety) as variety_count')
            ->groupBy('crop_variety')
            ->orderBy('variety_count', 'DESC')
            ->limit(10)
            ->findAll();

        $varietyNames = array_column($cropVarietyCount, 'crop_variety');
        $varietyCounts = array_column($cropVarietyCount, 'variety_count');

        $chartData2 = [
            'labels' => $varietyNames,
            'datasets' => [
                [
                    'label' => 'Number of Crop Varieties',
                    'backgroundColor' => 'rgb(250, 208, 92)',
                    'borderColor' => 'rgb(250, 208, 92)',
                    'borderWidth' => 1,
                    'data' => $varietyCounts,
                ],
            ],
        ];

        $damageCounts = $this->damages
            ->select('pest_type, COUNT(*) as count')
            ->groupBy('pest_type')
            ->findAll();

        $pestTypes = array_column($damageCounts, 'pest_type');
        $damageCountsData = array_column($damageCounts, 'count');

        $chartData3 = [
            'labels' => $pestTypes,
            'datasets' => [
                [
                    'label' => 'Number of Damages',
                    'backgroundColor' => 'rgb(250, 108, 92)',
                    'borderColor' => 'rgb(250, 108, 92)',
                    'borderWidth' => 1,
                    'data' => $damageCountsData,
                ],
            ],
        ];
        $weatherEventCounts = $this->damages
            ->select('weather_events, COUNT(*) as count')
            ->groupBy('weather_events')
            ->findAll();

        $weatherEvents = array_column($weatherEventCounts, 'weather_events');
        $weatherEventCountsData = array_column($weatherEventCounts, 'count');

        $chartData4 = [
            'labels' => $weatherEvents,
            'datasets' => [
                [
                    'label' => 'Number of Damages',
                    'backgroundColor' => 'rgb(92, 182, 250)',
                    'borderColor' => 'rgb(92, 182, 250)',
                    'borderWidth' => 1,
                    'data' => $weatherEventCountsData,
                ],
            ],
        ];
        $harvestData = $this->harvest
            ->select('harvest_date, harvest_quantity')
            ->findAll();

        $harvestDates = array_column($harvestData, 'harvest_date');
        $harvestQuantities = array_column($harvestData, 'harvest_quantity');

        $chartData6 = [
            'labels' => $harvestDates,
            'datasets' => [
                [
                    'label' => 'Harvest Quantity',
                    'backgroundColor' => 'rgb(54, 162, 235)',
                    'borderColor' => 'rgb(54, 162, 235)',
                    'data' => $harvestQuantities,
                ],
            ],
        ];
        $data = [
            'chartData' => $chartData,
            'chartData2' => $chartData2,
            'chartData3' => $chartData3,
            'chartData4' => $chartData4,
            'chartData6' => $chartData6,
        ];
        return view('adminfolder/viewcharts', $data);
    }

    /*public function importExcel()
    {
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            if (isset($_FILES["excel_file"]) && $_FILES["excel_file"]["error"] == UPLOAD_ERR_OK) {
                $uploadDirectory = __DIR__ . '/uploads/excel';

                if (!file_exists($uploadDirectory)) {
                    mkdir($uploadDirectory, 0777, true);
                }

                $fileTmpName = $_FILES["excel_file"]["tmp_name"];
                $fileName = $_FILES["excel_file"]["name"];

                move_uploaded_file($fileTmpName, $uploadDirectory . $fileName);

                $spreadsheet = IOFactory::load($uploadDirectory . $fileName);

                $worksheet = $spreadsheet->getActiveSheet();

                $highestRow = $worksheet->getHighestRow();
                $highestColumn = $worksheet->getHighestColumn();
                $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

                $pdo = new PDO('mysql:host=localhost;dbname=final_agrismart', 'root', '');
                // Modify your SQL statement to exclude the field_id column
                $stmt = $pdo->prepare("INSERT INTO fields (farmer_name, field_name, field_owner, field_address, field_total_area, user_id, fims_code) VALUES (?, ?, ?, ?, ?, ?, ?)");

                // Modify the loop to adjust for the excluded field_id column
                for ($row = 2; $row <= $highestRow; ++$row) {
                    $rowData = [];

                    // Fetch the farmer name from the Excel file
                    $farmerName = $worksheet->getCell('A' . $row)->getValue();

                    // Skip reading the first column (farmer name) and start from the second column
                    for ($col = 2; $col <= $highestColumnIndex; ++$col) {
                        $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);

                        $cellValue = $worksheet->getCell($columnLetter . $row)->getValue();
                        $rowData[] = $cellValue;
                    }

                    // Fetch fims_code from the database based on the farmer name
                    $profile = $this->profiles->where('fullname', $farmerName)->first();
                    if ($profile) {
                        $fimsCode = $profile['fims_code'];
                    } else {
                        $fimsCode = null;
                    }

                    // Add user_id and fims_code to $rowData
                    $userId = session()->get('leader_id');
                    $rowData[] = $userId;
                    $rowData[] = $fimsCode;

                    // Execute the SQL statement with the extracted data
                    $stmt->execute($rowData);
                }



                $pdo = null;

                echo "Data imported successfully.";
            } else {
                echo "Please upload an Excel file.";
            }
        }
    }*/
}
