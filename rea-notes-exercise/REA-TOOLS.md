# REA tool inventory

Captured from the connected REA 6.7.0 server on 2026-10-21 (Asia/Singapore).
207 tools are exposed; 77 report available in this target-free Windows session. Availability describes server prerequisites, not proof that every runtime endpoint or optional executable is ready.

## official-proxy — target_required

- `address_name`
- `address_to_file_offset`
- `comment`
- `current_address`
- `current_document`
- `current_procedure`
- `inline_comment`
- `inspect_native_data_type`
- `inspect_native_instruction`
- `inspect_native_load_image`
- `list_bookmarks`
- `list_documents`
- `list_names`
- `list_procedures`
- `list_segments`
- `list_strings`
- `next_address`
- `prev_address`
- `procedure_address`
- `procedure_assembly`
- `procedure_callees`
- `procedure_callers`
- `procedure_info`
- `procedure_pseudo_code`
- `procedure_references`
- `read_bytes`
- `read_function_instructions`
- `resolve_containing_procedure`
- `resolve_native_call_targets`
- `search_bytes`
- `search_procedures`
- `search_strings`
- `xrefs`

Requirement: Call open_binary with a supported local target.

## enhanced — target_required

- `analyze_function`
- `analyze_swift_types`
- `batch_decompile`
- `binary_overview`
- `find_xrefs_to_name`
- `get_call_graph`
- `get_objc_classes`
- `get_objc_protocols`
- `inspect_native_dispatch_metadata`
- `trace_native_ui_action`
- `trace_native_values`

Requirement: Call open_binary with a supported local target.

## electron-provider — available

- `analyze_javascript_application`

## browser-provider — available

- `analyze_web_bundle`
- `capture_web_screenshot`
- `discover_webmcp_tools`
- `inspect_web_event_listeners`
- `inspect_web_page`
- `list_browser_targets`
- `observe_web_session`

## official-proxy — target_required

- `annotate_native_function`

Requirement: Call open_binary with a supported local target.

## session — available

- `binary_session`

## session — available

- `build_call_path`
- `compare_artifacts`
- `compare_bundles`
- `compare_functions`
- `compare_process_captures`
- `correlate_static_and_runtime`
- `find_changed_behavior`
- `import_evidence_bundle`
- `inspect_address_context`
- `verify_reconstruction`

## application — available

- `build_reconstruction_obligation_ledger`
- `compare_application_versions`
- `compare_javascript_export_shapes`
- `compare_managed_members`
- `compare_source_to_bundle`
- `compare_wasm_interfaces`
- `import_managed_reconstruction`
- `inspect_analysis_view`
- `project_android_application_graph`
- `project_apple_application_graph`
- `project_harmony_application_graph`
- `project_managed_application_graph`
- `trace_application_feature`
- `trace_javascript_semantics`
- `verify_managed_native_boundaries`

## adb-provider — provider_unavailable

- `capture_adb_screen`
- `collect_adb_bugreport`
- `pull_adb_file`
- `pull_adb_package`

Requirement: The selected adb binary was not found: adb

## browser-provider — available

- `capture_browser_scenario`

## electron-provider — available

- `capture_electron_scenario`

## native-provider — unsupported_host

- `capture_native_ui_scenario`

Requirement: Native macOS utilities require macOS.

## session — unsupported_host

- `capture_process_scenario`

Requirement: Run process capture on a supported Linux or macOS host.

## session — available

- `close_binary`

## native-provider — available

- `close_frida_session`
- `start_frida_session`
- `unload_frida_script`

## native-provider — target_required

- `close_gdb_session`
- `start_rizin_debug_session`

Requirement: Call open_binary with a supported local target.

## native-provider — target_required

- `close_rizin_debug_session`

Requirement: Call open_binary with a supported local target.

## browser-provider — available

- `compare_web_captures`
- `compare_web_screenshots`

## native-provider — target_required

- `cutter_command`

Requirement: Call open_binary with a supported local target.

## apktool-provider — provider_unavailable

- `decode_android_resources`

Requirement: The apktool launcher was not found on the selected Windows PATH/PATHEXT

## artifact-provider — target_required

- `decode_interface_builder`
- `inspect_keyed_archive`
- `trace_dylib_resolution`

Requirement: Call open_binary with a supported local target.

## artifact-provider — provider_missing

- `decode_signal_recording`
- `inspect_signal_decoders`

Requirement: On Linux, supply sigrok-cli 0.7.2 via absolute REA_SIGROK_COMMAND and util-linux prlimit (REA_SIGROK_PRLIMIT_COMMAND, default /usr/bin/prlimit). No active binary target or hardware is required; the tool is checked at execution.

## jeb-provider — provider_missing

- `decompile_jeb_item`
- `inspect_jeb_client`
- `list_jeb_units`

Requirement: JEB MCP catalog failed

## native-provider — unsupported_host

- `demangle_swift`
- `inspect_macho`
- `inspect_plist`
- `inspect_signature`
- `list_architectures`

Requirement: Native macOS utilities require macOS.

## application — available

- `evaluate_reconstruction_coverage`

## native-provider — target_required

- `execute_rizin_command`

Requirement: Call open_binary with a supported local target.

## session — available

- `export_evidence_bundle`

## application — available

- `export_web_scripts`

## artifact-provider — target_required

- `extract_artifact`

Requirement: Call open_binary with a supported local target.

## firmware-provider — provider_missing

- `extract_firmware`

Requirement: On Linux, provide an absolute REA_BINWALK_COMMAND (Binwalk 3.1.x, verified with 3.1.0) or REA_UNBLOB_COMMAND (Unblob 26.6.x, verified with 26.6.4) path and util-linux prlimit. Extraction also requires the selected format’s external extractor.

## native-provider — available

- `frida_session_status`
- `inspect_pe_resources`

## native-provider — target_required

- `gdb_console`

Requirement: Call open_binary with a supported local target.

## native-provider — target_required

- `gdb_session_status`

Requirement: Call open_binary with a supported local target.

## session — available

- `get_evidence_bundle`
- `list_unknowns`
- `verify_unknown_resolution`

## session — provider_missing

- `get_navigation_context`

Requirement: Configure a provider for one of: current_document, current_address, resolve_containing_procedure.

## official-proxy — target_required

- `goto_address`

Requirement: Call open_binary with a supported local target.

## flutter-provider — available

- `identify_flutter_build`
- `inspect_dart_aot`

## adb-provider — provider_unavailable

- `inspect_adb_client`

Requirement: The selected adb binary was not found: adb

## adb-provider — provider_unavailable

- `inspect_adb_device`
- `inspect_adb_display`
- `inspect_adb_package`
- `inspect_adb_window`
- `list_adb_devices`
- `list_adb_directory`
- `list_adb_features`
- `list_adb_packages`
- `list_adb_processes`
- `list_adb_services`
- `read_adb_logcat`
- `read_adb_setting`
- `resolve_adb_packages`

Requirement: The selected adb binary was not found: adb

## android-provider — provider_missing

- `inspect_android_class`
- `inspect_android_method`
- `inspect_android_package`
- `search_android_classes`
- `trace_android_references`

Requirement: Set REA_JADX_MCP_JAR to the absolute path of a caller-supplied jadx-headless-mcp 0.7.1 JAR. REA does not download or install it.

## apktool-provider — provider_unavailable

- `inspect_apktool_client`

Requirement: The apktool launcher was not found on the selected Windows PATH/PATHEXT

## artifact-provider — target_required

- `inspect_artifact`

Requirement: Call open_binary with a supported local target.

## artifact-provider — unsupported_host

- `inspect_asset_catalog`

Requirement: Apple asset catalogs require macOS assetutil.

## native-provider — provider_missing

- `inspect_binary_layout`
- `inspect_recorded_crash`

Requirement: On Linux x64, set absolute REA_PWNTOOLS_PYTHON to caller-supplied Python with pwntools 4.15.0. No active binary target is required.

## artifact-provider — available

- `inspect_electron_fuses`
- `inspect_go_binary`
- `inspect_rust_binary`

## electron-provider — available

- `inspect_electron_page`
- `list_electron_targets`

## artifact-provider — provider_missing

- `inspect_evm_interface`

Requirement: The bundled offline EVM interface profile is currently real-verified on Linux x64. No active binary target or chain endpoint is required.

## firmware-provider — provider_missing

- `inspect_firmware_regions`

Requirement: On Linux, provide an absolute REA_BINWALK_COMMAND (Binwalk 3.1.x, verified with 3.1.0) or REA_UNBLOB_COMMAND (Unblob 26.6.x, verified with 26.6.4) path and util-linux prlimit. Extraction also requires the selected format’s external extractor.

## managed-provider — available

- `inspect_managed_artifact`
- `inspect_managed_members`
- `inspect_managed_native_boundaries`

## enhanced — target_required

- `inspect_native_api`
- `trace_call_path`
- `trace_feature`

Requirement: Call open_binary with a supported local target.

## artifact-provider — provider_missing

- `inspect_packet_capture`
- `inspect_packet_fields`

Requirement: On Linux, supply TShark 4.x via absolute REA_TSHARK_COMMAND and util-linux prlimit (REA_TSHARK_PRLIMIT_COMMAND, default /usr/bin/prlimit). No active binary target or hardware is required; the tool is checked at execution.

## pcap-provider — available

- `inspect_pcap_capture`
- `inspect_pcap_client`

## artifact-provider — provider_unavailable

- `inspect_sqlite_database`

Requirement: SQLite inspection requires Node.js 24.x >=24.15 or 26+ with the native DatabaseSync.limits API. Other REA features retain their published runtime requirements. The inspection worker verifies the API before querying the snapshot.

## artifact-provider — provider_missing

- `inspect_wasm_artifact`

Requirement: Supply WABT 1.0.42 via absolute REA_WABT_BIN_DIRECTORY. No active binary target is required; tools are checked at execution.

## browser-provider — available

- `inspect_web_network_capture`

## native-provider — target_required

- `inspect_with_objdump`

Requirement: Call open_binary with a supported local target.

## adb-provider — provider_unavailable

- `install_adb_package`
- `start_adb_activity`
- `start_adb_app`
- `stop_adb_app`
- `uninstall_adb_package`

Requirement: The selected adb binary was not found: adb

## native-provider — available

- `instrument_with_frida`
- `load_frida_script`
- `resume_frida_session`

## native-provider — target_required

- `list_cutter_sessions`

Requirement: Call open_binary with a supported local target.

## native-provider — available

- `list_frida_devices`
- `list_frida_processes`

## runtime-provider — available

- `list_javascript_runtime_targets`
- `observe_javascript_runtime`

## native-provider — unsupported_host

- `observe_native_calls`

Requirement: Native macOS utilities require macOS.

## native-provider — unsupported_host

- `observe_native_ui`

Requirement: Native macOS utilities require macOS.

## browser-provider — available

- `observe_web_execution`

## session — available

- `open_binary`

## jeb-provider — provider_missing

- `open_jeb_project`

Requirement: JEB MCP catalog failed

## adb-provider — provider_unavailable

- `push_adb_file`

Requirement: The selected adb binary was not found: adb

## electron-provider — available

- `reconcile_javascript_runtime`

## session — available

- `record_unknown`
- `update_unknown`

## application — provider_missing

- `recover_javascript_sources`

Requirement: On Linux x64, provide an absolute REA_WAKARU_COMMAND for Wakaru ^1.13.0 (verified with 1.14.0) and util-linux prlimit; see docs/javascript-recovery.md#install-wakaru. No binary target is required.

## native-provider — target_required

- `rizin_debug_command`

Requirement: Call open_binary with a supported local target.

## native-provider — target_required

- `rizin_debug_session_status`

Requirement: Call open_binary with a supported local target.

## official-proxy — target_required

- `set_address_name`
- `set_addresses_names`
- `set_bookmark`
- `set_comment`
- `set_inline_comment`
- `unset_bookmark`

Requirement: Call open_binary with a supported local target.

## native-provider — target_required

- `start_gdb_session`

Requirement: Call open_binary with a supported local target.

## application — available

- `trace_web_module_imports`

## application — available

- `trace_web_source_location`
