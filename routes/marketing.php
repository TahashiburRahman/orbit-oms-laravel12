<?php

use App\Http\Controllers\BriefController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ClientQueryController;
use App\Http\Controllers\LeadController;
use Illuminate\Support\Facades\Route;

Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert');
Route::resource('leads', LeadController::class)->except('destroy');
Route::post('campaigns/{campaign}/posts', [CampaignController::class, 'storePost'])->name('campaigns.posts.store');
Route::get('posts/{post}/edit', [CampaignController::class, 'editPost'])->name('posts.edit');
Route::put('posts/{post}', [CampaignController::class, 'updatePost'])->name('posts.update');
Route::resource('campaigns', CampaignController::class)->except('destroy');
Route::post('briefs/{brief}/claim', [BriefController::class, 'claim'])->name('briefs.claim');
Route::post('briefs/{brief}/upload', [BriefController::class, 'upload'])->name('briefs.upload');
Route::post('briefs/{brief}/submit', [BriefController::class, 'submit'])->name('briefs.submit');
Route::post('briefs/{brief}/review', [BriefController::class, 'review'])->name('briefs.review');
Route::get('files/{deliverable}', [BriefController::class, 'download'])->name('files.download');
Route::resource('briefs', BriefController::class)->except('destroy');
Route::post('queries/{query}/messages', [ClientQueryController::class, 'message'])->name('queries.messages.store');
Route::resource('queries', ClientQueryController::class)->except('destroy');
