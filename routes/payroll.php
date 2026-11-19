<?php

use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\PayrollController;
use Illuminate\Support\Facades\Route;

Route::resource('employees', EmployeeController::class);
Route::get('payroll/{payroll}/export', [PayrollController::class, 'export'])->name('payroll.export');
Route::get('payroll/{payroll}/entries/{entry}/payslip', [PayrollController::class, 'payslip'])->name('payroll.payslip');
Route::patch('payroll/{payroll}/entries/{entry}', [PayrollController::class, 'updateEntry'])->name('payroll.entries.update');
Route::post('payroll/{payroll}/entries/{entry}/correction', [PayrollController::class, 'correct'])->name('payroll.correct');
Route::post('payroll/{payroll}/submit', [PayrollController::class, 'submit'])->name('payroll.submit');
Route::post('payroll/{payroll}/approve', [PayrollController::class, 'approve'])->name('payroll.approve');
Route::post('payroll/{payroll}/pay', [PayrollController::class, 'pay'])->name('payroll.pay');
Route::resource('payroll', PayrollController::class)->except('destroy');
