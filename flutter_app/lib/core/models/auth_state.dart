class AuthState {
  const AuthState({
    this.token,
    this.entrypoint,
    this.tenantId,
    this.permissions = const [],
  });

  final String? token;
  final String? entrypoint;
  final String? tenantId;
  final List<String> permissions;

  bool get isLoggedIn => token != null && token!.isNotEmpty;

  AuthState copyWith({
    String? token,
    String? entrypoint,
    String? tenantId,
    List<String>? permissions,
  }) {
    return AuthState(
      token: token ?? this.token,
      entrypoint: entrypoint ?? this.entrypoint,
      tenantId: tenantId ?? this.tenantId,
      permissions: permissions ?? this.permissions,
    );
  }
}
