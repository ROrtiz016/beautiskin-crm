<?php

namespace App\Http\Controllers\Spa;

use App\Http\Controllers\TreatmentPackageWebController;
use Illuminate\Http\JsonResponse;

class PackagesSpaController extends TreatmentPackageWebController
{
    public function index(): JsonResponse
    {
        return response()->json($this->packagesIndexPayload());
    }
}
