class AuthState {
  const AuthState({this.token, this.entrypoint});

  final String? token;
  final String? entrypoint;

  bool get isLoggedIn => token != null && token!.isNotEmpty;

  AuthState copyWith({String? token, String? entrypoint}) {
    return AuthState(
      token: token ?? this.token,
      entrypoint: entrypoint ?? this.entrypoint,
    );
  }
}
