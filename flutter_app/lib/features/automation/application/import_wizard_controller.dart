import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/dio_client.dart';
import '../../auth/auth_controller.dart';

final importWizardControllerProvider = NotifierProvider.autoDispose<ImportWizardController, ImportWizardState>(
  ImportWizardController.new,
);

class ImportWizardState {
  const ImportWizardState({
    this.isLoading = false,
    this.error,
    this.preview,
    this.executeResult,
    this.workerResult,
  });

  final bool isLoading;
  final String? error;
  final Map<String, dynamic>? preview;
  final Map<String, dynamic>? executeResult;
  final Map<String, dynamic>? workerResult;

  ImportWizardState copyWith({
    bool? isLoading,
    String? error,
    bool clearError = false,
    Map<String, dynamic>? preview,
    bool clearPreview = false,
    Map<String, dynamic>? executeResult,
    bool clearExecuteResult = false,
    Map<String, dynamic>? workerResult,
  }) {
    return ImportWizardState(
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
      preview: clearPreview ? null : (preview ?? this.preview),
      executeResult: clearExecuteResult ? null : (executeResult ?? this.executeResult),
      workerResult: workerResult ?? this.workerResult,
    );
  }
}

class ImportWizardController extends Notifier<ImportWizardState> {
  @override
  ImportWizardState build() => const ImportWizardState();

  Future<void> preview({required String dataset, required String rawRowsJson}) async {
    await _run(() async {
      final rows = _parseRows(rawRowsJson);
      final response = await _dio.post(
        '/billing/automation/import/preview',
        data: {'dataset': dataset, 'rows': rows},
        options: _options(),
      );
      state = state.copyWith(preview: Map<String, dynamic>.from(response.data['data'] as Map? ?? const {}));
    });
  }

  Future<void> execute({required String dataset, required String rawRowsJson}) async {
    await _run(() async {
      final rows = _parseRows(rawRowsJson);
      final response = await _dio.post(
        '/billing/automation/import/execute',
        data: {'dataset': dataset, 'rows': rows},
        options: _options(),
      );
      state = state.copyWith(executeResult: Map<String, dynamic>.from(response.data['data'] as Map? ?? const {}));
    });
  }

  Future<void> processWorkerQueue({int limit = 25}) async {
    await _run(() async {
      final response = await _dio.post(
        '/billing/automation/workflows/process',
        data: {'limit': limit},
        options: _options(),
      );
      state = state.copyWith(workerResult: Map<String, dynamic>.from(response.data['data'] as Map? ?? const {}));
    });
  }

  List<Map<String, dynamic>> _parseRows(String rawRowsJson) {
    final parsed = jsonDecode(rawRowsJson);
    if (parsed is! List) {
      throw const FormatException('JSON root must be an array.');
    }

    return parsed.whereType<Map>().map((row) => Map<String, dynamic>.from(row)).toList(growable: false);
  }

  Future<void> _run(Future<void> Function() action) async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      await action();
    } on FormatException catch (error) {
      state = state.copyWith(error: error.message);
    } on DioException catch (error) {
      state = state.copyWith(error: error.response?.data.toString() ?? error.message);
    } catch (error) {
      state = state.copyWith(error: error.toString());
    } finally {
      state = state.copyWith(isLoading: false);
    }
  }

  Dio get _dio => ref.read(dioProvider);

  Options _options() {
    final authState = ref.read(authControllerProvider);
    return Options(
      headers: {
        'X-Tenant-Id': authState.tenantId ?? 'tenant_1',
        if ((authState.userId ?? '').isNotEmpty) 'X-User-Id': authState.userId!,
        if ((authState.token ?? '').isNotEmpty) 'Authorization': 'Bearer ${authState.token}',
      },
    );
  }
}
