@extends('errors::minimal')

@section('title', __('Technical Issue'))
@section('code', '500')
@section('message', __('System Interruption'))
@section('details', __('A secure background process was suddenly interrupted. Do not worry — our system has automatically recorded this for the IT team. Please go back and try your action again.'))
