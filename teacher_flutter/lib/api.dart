import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

typedef Json = Map<String, dynamic>;

class ApiError implements Exception {
  final int status;
  final String message;
  ApiError(this.status, this.message);
  @override
  String toString() => message;
}

class NetworkError implements Exception {
  @override
  String toString() => 'تعذر الاتصال بالخادم. تحقق من الاتصال وحاول مجددًا.';
}

class TeacherApi {
  static const configuredBase = String.fromEnvironment('API_URL');
  static String get base => configuredBase.isNotEmpty
      ? configuredBase
      : 'https://h.oussamanh.com/api';
  static const authKey = 'halaka-teacher-auth';
  final http.Client client;
  String? token;
  Json? profile;
  void Function()? onUnauthorized;
  TeacherApi({http.Client? client}) : client = client ?? http.Client();

  Future<void> restore() async {
    final prefs = await SharedPreferences.getInstance();
    try {
      final saved = jsonDecode(prefs.getString(authKey) ?? 'null');
      if (saved is Map && saved['token'] is String && saved['profile'] is Map) {
        token = saved['token'];
        profile = Map<String, dynamic>.from(saved['profile']);
      }
    } catch (_) {
      await prefs.remove(authKey);
    }
  }

  Future<Json> request(String method, String path, [Json? body]) async {
    final requestToken = token;
    final request = http.Request(
      method,
      Uri.parse('${base.replaceFirst(RegExp(r'/$'), '')}$path'),
    );
    request.headers.addAll({
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'Cache-Control': 'no-store',
      if (requestToken != null) 'Authorization': 'Bearer $requestToken',
    });
    if (body != null) request.body = jsonEncode(body);
    final http.Response response;
    try {
      response = await http.Response.fromStream(await client.send(request))
          .timeout(const Duration(seconds: 25));
    } on http.ClientException {
      throw NetworkError();
    } on TimeoutException {
      throw NetworkError();
    }
    if (response.statusCode == 401 &&
        path != '/login' &&
        requestToken != null &&
        token == requestToken) {
      await forget();
      onUnauthorized?.call();
    }
    Json data;
    try {
      data = Map<String, dynamic>.from(jsonDecode(response.body));
    } catch (_) {
      throw ApiError(response.statusCode, 'استجابة غير صالحة من الخادم');
    }
    if (response.statusCode >= 400) {
      final errors = data['errors'];
      throw ApiError(
        response.statusCode,
        errors is Map
            ? errors.values.expand((v) => v is List ? v : [v]).join('\n')
            : (data['message'] ?? 'تعذر الاتصال').toString(),
      );
    }
    return data;
  }

  Future<void> login(String email, String password) async {
    final data = await request('POST', '/login', {
      'email': email,
      'password': password,
    });
    token = data['access_token'];
    profile = Map<String, dynamic>.from(data['data']);
    await (await SharedPreferences.getInstance()).setString(
      authKey,
      jsonEncode({'token': token, 'profile': profile}),
    );
  }

  Future<void> logout() async {
    await request('POST', '/logout');
    await forget();
  }

  Future<void> forget() async {
    await (await SharedPreferences.getInstance()).remove(authKey);
    token = null;
    profile = null;
  }
}
