class AuthState {
  const AuthState({
    this.token,
    this.userId,
    this.entrypoint,
    this.tenantId,
    this.companyId,
    this.permissions = const [],
  });

  final String? token;
  final String? userId;
  final String? entrypoint;
  final String? tenantId;
  final String? companyId;
  final List<String> permissions;

  bool get isLoggedIn => token != null && token!.isNotEmpty;

  AuthState copyWith({
    String? token,
    String? userId,
    String? entrypoint,
    String? tenantId,
    String? companyId,
    List<String>? permissions,
  }) {
    return AuthState(
      token: token ?? this.token,
      userId: userId ?? this.userId,
      entrypoint: entrypoint ?? this.entrypoint,
      tenantId: tenantId ?? this.tenantId,
      companyId: companyId ?? this.companyId,
      permissions: permissions ?? this.permissions,
    );
  }
}
