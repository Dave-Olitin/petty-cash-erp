@extends('errors::minimal')

@section('title', __('Secure Session Expired'))
@section('code', '419')
@section('message', __('Session Expired'))
@section('details', __('For your security, your session has expired due to inactivity. No data was compromised. Please return to the dashboard to safely resume your work.'))
