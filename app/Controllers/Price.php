<?php
namespace App\Controllers;

use App\Models\PriceModel;
use App\Models\UserModel;

class Price extends BaseController
{
    protected $priceModel;

    public function __construct()
    {
        $this->priceModel = new PriceModel();
    }

    /**
     * GET: Show price page with active durations only
     */
    public function index()
    {
        $user = (new UserModel())->getUser();

        // Sirf active durations (is_active=1)
        try {
            $rows = $this->priceModel
                ->where('is_active', 1)
                ->orderBy('duration', 'ASC')
                ->findAll();
        } catch (\Throwable $e) {
            // Fallback (agar is_active column abhi add nahi hua)
            $rows = $this->priceModel->orderBy('duration', 'ASC')->findAll();
        }

        $durations = [];
        $map       = [];
        foreach ($rows as $r) {
            $d = (int) $r['duration'];
            $p = (int) $r['price'];
            $durations[] = $d;
            $map[$d]     = $p;
        }

        return view('Price/index', [
            'title'     => 'Price Update',
            'durations' => $durations, // dropdown options (active only)
            'overrides' => $map,       // duration => price (active only)
            'user'      => $user
        ]);
    }

    /**
     * POST: Add/Activate a duration with price
     * Route: POST /price/add
     * Body: duration, price
     */
    public function addDuration()
    {
        $duration = (int) $this->request->getPost('duration');
        $price    = (int) $this->request->getPost('price');

        if ($duration <= 0) {
            return redirect()->back()->with('err', 'Duration must be greater than 0.');
        }
        if ($price <= 0) {
            return redirect()->back()->with('err', 'Price must be greater than 0.');
        }

        // Upsert: agar row hai to activate + update price, warna insert
        $existing = $this->priceModel->find($duration);
        if ($existing) {
            // Soft-activate back
            $this->priceModel->update($duration, [
                'price'     => $price,
                'is_active' => 1,
            ]);
            return redirect()->to('/price')->with('msg', 'Duration re-activated & price updated.');
        } else {
            $data = [
                'duration'  => $duration,
                'price'     => $price,
            ];
            // is_active column ho to set karein
            if ($this->hasIsActive()) $data['is_active'] = 1;

            $this->priceModel->insert($data);
            return redirect()->to('/price')->with('msg', 'Duration added successfully.');
        }
    }

    /**
     * POST: Update price for an existing active duration
     * Route: POST /price/update
     * Body: duration, price
     */
    public function updatePrice()
    {
        $duration = (int) $this->request->getPost('duration');
        $price    = (int) $this->request->getPost('price');

        if ($duration <= 0) {
            return redirect()->back()->with('err', 'Invalid duration.');
        }
        if ($price <= 0) {
            return redirect()->back()->with('err', 'Price must be greater than 0.');
        }

        // Ensure duration exists & is active
        $row = $this->priceModel->find($duration);
        if (!$row) {
            return redirect()->back()->with('err', 'Duration not found.');
        }
        if ($this->hasIsActive() && isset($row['is_active']) && (int)$row['is_active'] !== 1) {
            return redirect()->back()->with('err', 'Duration is not active.');
        }

        $this->priceModel->update($duration, ['price' => $price]);
        return redirect()->to('/price')->with('msg', 'Price updated successfully.');
    }

    /**
     * POST: Soft-remove a duration (hide from UI)
     * Route: POST /price/delete/{duration}
     */
    public function delete($duration)
    {
        $duration = (int) $duration;
        if ($duration <= 0) {
            return redirect()->back()->with('err', 'Invalid duration.');
        }

        // Soft delete: is_active=0 (agar column hai)
        if ($this->hasIsActive()) {
            $this->priceModel->update($duration, ['is_active' => 0]);
        } else {
            // Column nahi hai to hard delete (last resort)
            $this->priceModel->delete($duration);
        }

        return redirect()->to('/price')->with('msg', 'Duration removed.');
    }

    /**
     * Helper: check if price_config has `is_active` column
     */
    private function hasIsActive(): bool
    {
        try {
            $fields = $this->priceModel->getFieldNames($this->priceModel->getTable());
            return in_array('is_active', $fields, true);
        } catch (\Throwable $e) {
            return false;
        }
    }
}