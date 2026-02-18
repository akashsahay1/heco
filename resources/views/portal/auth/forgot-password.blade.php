@extends('portal.layout')
@section('title', 'Forgot Password - HECO Portal')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h3 class="text-center mb-4">Forgot Password</h3>

                    @if(session('status'))
                        <div class="alert alert-success">{{ session('status') }}</div>
                    @endif

                    <form method="POST" action="/forgot-password">
                        @csrf
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                            @error('email')
                                <div class="text-danger small">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-success w-100">Send Reset Link</button>
                    </form>

                    <p class="text-center mt-3 mb-0">
                        <a href="/login" class="text-success">Back to Login</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
