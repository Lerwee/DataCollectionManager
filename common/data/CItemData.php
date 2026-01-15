<?php declare(strict_types = 0);
namespace app\customs\zapi\common\data;

/**
 * Class containing information about Items.
 */
final class CItemData {

	public const KEYS_BY_TYPE = [
		ITEM_TYPE_PERSEUS => [
			'agent.hostmetadata',
			'agent.hostname',
			'agent.ping',
			'agent.variant',
			'agent.version',
			'kernel.maxfiles',
			'kernel.maxproc',
			'kernel.openfiles',
			'modbus.get[endpoint,<slaveid>,<function>,<address>,<count>,<type>,<endianness>,<offset>]',
			'net.dns.record[<ip>,name,<type>,<timeout>,<count>,<protocol>]',
			'net.dns[<ip>,name,<type>,<timeout>,<count>,<protocol>]',
			'net.if.collisions[if]',
			'net.if.discovery',
			'net.if.in[if,<mode>]',
			'net.if.list',
			'net.if.out[if,<mode>]',
			'net.if.total[if,<mode>]',
			'net.tcp.listen[port]',
			'net.tcp.port[<ip>,port]',
			'net.tcp.service.perf[service,<ip>,<port>]',
			'net.tcp.service[service,<ip>,<port>]',
			'net.tcp.socket.count[<laddr>,<lport>,<raddr>,<rport>,<state>]',
			'net.udp.listen[port]',
			'net.udp.service.perf[service,<ip>,<port>]',
			'net.udp.service[service,<ip>,<port>]',
			'net.udp.socket.count[<laddr>,<lport>,<raddr>,<rport>,<state>]',
			'perf_counter[counter,<interval>]',
			'perf_counter_en[counter,<interval>]',
			'perf_instance.discovery[object]',
			'perf_instance_en.discovery[object]',
			'proc.cpu.util[<name>,<user>,<type>,<cmdline>,<mode>,<zone>]',
			'proc.get[<name>,<user>,<cmdline>,<mode>]',
			'proc.mem[<name>,<user>,<mode>,<cmdline>,<memtype>]',
			'proc.num[<name>,<user>,<state>,<cmdline>,<zone>]',
			'proc_info[process,<attribute>,<type>]',
			'registry.data[key,<value name>]',
			'registry.get[key,<mode>,<name regexp>]',
			'sensor[device,sensor,<mode>]',
			'service.info[service,<param>]',
			'services[<type>,<state>,<exclude>]',
			'system.boottime',
			'system.cpu.discovery',
			'system.cpu.intr',
			'system.cpu.load[<cpu>,<mode>]',
			'system.cpu.num[<type>]',
			'system.cpu.switches',
			'system.cpu.util[<cpu>,<type>,<mode>,<logical_or_physical>]',
			'system.hostname[<type>,<transform>]',
			'system.hw.chassis[<info>]',
			'system.hw.cpu[<cpu>,<info>]',
			'system.hw.devices[<type>]',
			'system.hw.macaddr[<interface>,<format>]',
			'system.localtime[<type>]',
			'system.run[command,<mode>]',
			'system.stat[resource,<type>]',
			'system.sw.arch',
			'system.sw.os[<info>]',
			'system.sw.os.get',
			'system.sw.packages[<regexp>,<manager>,<format>]',
			'system.sw.packages.get[<regexp>,<manager>]',
			'system.swap.in[<device>,<type>]',
			'system.swap.out[<device>,<type>]',
			'system.swap.size[<device>,<type>]',
			'system.uname',
			'system.uptime',
			'system.users.num',
			'vfs.dev.discovery',
			'vfs.dev.read[<device>,<type>,<mode>]',
			'vfs.dev.write[<device>,<type>,<mode>]',
			'vfs.dir.count[dir,<regex_incl>,<regex_excl>,<types_incl>,<types_excl>,<max_depth>,<min_size>,<max_size>,<min_age>,<max_age>,<regex_excl_dir>]',
			'vfs.dir.get[dir,<regex_incl>,<regex_excl>,<types_incl>,<types_excl>,<max_depth>,<min_size>,<max_size>,<min_age>,<max_age>,<regex_excl_dir>]',
			'vfs.dir.size[dir,<regex_incl>,<regex_excl>,<mode>,<max_depth>,<regex_excl_dir>]',
			'vfs.file.cksum[file,<mode>]',
			'vfs.file.contents[file,<encoding>]',
			'vfs.file.exists[file,<types_incl>,<types_excl>]',
			'vfs.file.get[file]',
			'vfs.file.md5sum[file]',
			'vfs.file.owner[file,<ownertype>,<resulttype>]',
			'vfs.file.permissions[file]',
			'vfs.file.regexp[file,regexp,<encoding>,<start line>,<end line>,<output>]',
			'vfs.file.regmatch[file,regexp,<encoding>,<start line>,<end line>]',
			'vfs.file.size[file,<mode>]',
			'vfs.file.time[file,<mode>]',
			'vfs.fs.discovery',
			'vfs.fs.get',
			'vfs.fs.inode[fs,<mode>]',
			'vfs.fs.size[fs,<mode>]',
			'vm.memory.size[<mode>]',
			'vm.vmemory.size[<type>]',
			'web.page.get[host,<path>,<port>]',
			'web.page.perf[host,<path>,<port>]',
			'web.page.regexp[host,<path>,<port>,regexp,<length>,<output>]',
			'wmi.get[namespace,query]',
			'wmi.getall[namespace,query]',
//			'perseus.stats[<ip>,<port>,queue,<from>,<to>]',
//			'perseus.stats[<ip>,<port>]'
		],
		ITEM_TYPE_PERSEUS_ACTIVE => [
			'agent.hostmetadata',
			'agent.hostname',
			'agent.ping',
			'agent.variant',
			'agent.version',
			'eventlog[name,<regexp>,<severity>,<source>,<eventid>,<maxlines>,<mode>]',
			'kernel.maxfiles',
			'kernel.maxproc',
			'kernel.openfiles',
			'log.count[file,<regexp>,<encoding>,<maxproclines>,<mode>,<maxdelay>,<options>,<persistent_dir>]',
			'log[file,<regexp>,<encoding>,<maxlines>,<mode>,<output>,<maxdelay>,<options>,<persistent_dir>]',
			'logrt.count[file_regexp,<regexp>,<encoding>,<maxproclines>,<mode>,<maxdelay>,<options>,<persistent_dir>]',
			'logrt[file_regexp,<regexp>,<encoding>,<maxlines>,<mode>,<output>,<maxdelay>,<options>,<persistent_dir>]',
			'modbus.get[endpoint,<slaveid>,<function>,<address>,<count>,<type>,<endianness>,<offset>]',
			'mqtt.get[<broker_url>,topic,<username>,<password>]',
			'net.dns.record[<ip>,name,<type>,<timeout>,<count>,<protocol>]',
			'net.dns[<ip>,name,<type>,<timeout>,<count>,<protocol>]',
			'net.if.collisions[if]',
			'net.if.discovery',
			'net.if.in[if,<mode>]',
			'net.if.list',
			'net.if.out[if,<mode>]',
			'net.if.total[if,<mode>]',
			'net.tcp.listen[port]',
			'net.tcp.port[<ip>,port]',
			'net.tcp.service.perf[service,<ip>,<port>]',
			'net.tcp.service[service,<ip>,<port>]',
			'net.tcp.socket.count[<laddr>,<lport>,<raddr>,<rport>,<state>]',
			'net.udp.listen[port]',
			'net.udp.service.perf[service,<ip>,<port>]',
			'net.udp.service[service,<ip>,<port>]',
			'net.udp.socket.count[<laddr>,<lport>,<raddr>,<rport>,<state>]',
			'perf_counter[counter,<interval>]',
			'perf_counter_en[counter,<interval>]',
			'perf_instance.discovery[object]',
			'perf_instance_en.discovery[object]',
			'proc.cpu.util[<name>,<user>,<type>,<cmdline>,<mode>,<zone>]',
			'proc.get[<name>,<user>,<cmdline>,<mode>]',
			'proc.mem[<name>,<user>,<mode>,<cmdline>,<memtype>]',
			'proc.num[<name>,<user>,<state>,<cmdline>,<zone>]',
			'proc_info[process,<attribute>,<type>]',
			'registry.data[key,<value name>]',
			'registry.get[key,<mode>,<name regexp>]',
			'sensor[device,sensor,<mode>]',
			'service.info[service,<param>]',
			'services[<type>,<state>,<exclude>]',
			'system.boottime',
			'system.cpu.discovery',
			'system.cpu.intr',
			'system.cpu.load[<cpu>,<mode>]',
			'system.cpu.num[<type>]',
			'system.cpu.switches',
			'system.cpu.util[<cpu>,<type>,<mode>,<logical_or_physical>]',
			'system.hostname[<type>,<transform>]',
			'system.hw.chassis[<info>]',
			'system.hw.cpu[<cpu>,<info>]',
			'system.hw.devices[<type>]',
			'system.hw.macaddr[<interface>,<format>]',
			'system.localtime[<type>]',
			'system.run[command,<mode>]',
			'system.stat[resource,<type>]',
			'system.sw.arch',
			'system.sw.os[<info>]',
			'system.sw.os.get',
			'system.sw.packages[<regexp>,<manager>,<format>]',
			'system.sw.packages.get[<regexp>,<manager>]',
			'system.swap.in[<device>,<type>]',
			'system.swap.out[<device>,<type>]',
			'system.swap.size[<device>,<type>]',
			'system.uname',
			'system.uptime',
			'system.users.num',
			'vfs.dev.discovery',
			'vfs.dev.read[<device>,<type>,<mode>]',
			'vfs.dev.write[<device>,<type>,<mode>]',
			'vfs.dir.count[dir,<regex_incl>,<regex_excl>,<types_incl>,<types_excl>,<max_depth>,<min_size>,<max_size>,<min_age>,<max_age>,<regex_excl_dir>]',
			'vfs.dir.get[dir,<regex_incl>,<regex_excl>,<types_incl>,<types_excl>,<max_depth>,<min_size>,<max_size>,<min_age>,<max_age>,<regex_excl_dir>]',
			'vfs.dir.size[dir,<regex_incl>,<regex_excl>,<mode>,<max_depth>,<regex_excl_dir>]',
			'vfs.file.cksum[file,<mode>]',
			'vfs.file.contents[file,<encoding>]',
			'vfs.file.exists[file,<types_incl>,<types_excl>]',
			'vfs.file.get[file]',
			'vfs.file.md5sum[file]',
			'vfs.file.owner[file,<ownertype>,<resulttype>]',
			'vfs.file.permissions[file]',
			'vfs.file.regexp[file,regexp,<encoding>,<start line>,<end line>,<output>]',
			'vfs.file.regmatch[file,regexp,<encoding>,<start line>,<end line>]',
			'vfs.file.size[file,<mode>]',
			'vfs.file.time[file,<mode>]',
			'vfs.fs.discovery',
			'vfs.fs.get',
			'vfs.fs.inode[fs,<mode>]',
			'vfs.fs.size[fs,<mode>]',
			'vm.memory.size[<mode>]',
			'vm.vmemory.size[<type>]',
			'web.page.get[host,<path>,<port>]',
			'web.page.perf[host,<path>,<port>]',
			'web.page.regexp[host,<path>,<port>,regexp,<length>,<output>]',
			'wmi.get[namespace,query]',
//			'perseus.stats[<ip>,<port>,queue,<from>,<to>]',
//			'perseus.stats[<ip>,<port>]'
		],
		ITEM_TYPE_SIMPLE => [
			'icmpping[<target>,<packets>,<interval>,<size>,<timeout>]',
			'icmppingloss[<target>,<packets>,<interval>,<size>,<timeout>]',
			'icmppingsec[<target>,<packets>,<interval>,<size>,<timeout>,<mode>]',
			'net.tcp.service.perf[service,<ip>,<port>]',
			'net.tcp.service[service,<ip>,<port>]',
			'net.udp.service.perf[service,<ip>,<port>]',
			'net.udp.service[service,<ip>,<port>]',
			'vmware.alarms.get[url]',
			'vmware.cl.perfcounter[url,id,path,<instance>]',
			'vmware.cluster.alarms.get[url,id]',
			'vmware.cluster.discovery[url]',
			'vmware.cluster.property[url,id,prop]',
			'vmware.cluster.status[url,name]',
			'vmware.cluster.tags.get[url,id]',
			'vmware.datastore.alarms.get[url,uuid]',
			'vmware.datastore.discovery[url]',
			'vmware.datastore.hv.list[url,datastore]',
			'vmware.datastore.perfcounter[url,uuid,path,<instance>]',
			'vmware.datastore.property[url,uuid,prop]',
			'vmware.cluster.property[url,id,prop]',
			'vmware.datastore.read[url,datastore,<mode>]',
			'vmware.datastore.size[url,datastore,<mode>]',
			'vmware.datastore.tags.get[url,uuid]',
			'vmware.datastore.write[url,datastore,<mode>]',
			'vmware.dc.alarms.get[url,id]',
			'vmware.dc.discovery[url]',
			'vmware.dc.tags.get[url,id]',
			'vmware.dvswitch.discovery[url]',
			'vmware.dvswitch.fetchports.get[url,uuid,<filter>,<mode>]',
			'vmware.eventlog[url,<mode>]',
			'vmware.fullname[url]',
			'vmware.hv.alarms.get[url,uuid]',
			'vmware.hv.cluster.name[url,uuid]',
			'vmware.hv.connectionstate[url,uuid]',
			'vmware.hv.cpu.usage.perf[url,uuid]',
			'vmware.hv.cpu.usage[url,uuid]',
			'vmware.hv.cpu.utilization[url,uuid]',
			'vmware.hv.datacenter.name[url,uuid]',
			'vmware.hv.datastore.discovery[url,uuid]',
			'vmware.hv.datastore.list[url,uuid]',
			'vmware.hv.datastore.multipath[url,uuid,<datastore>,<partitionid>]',
			'vmware.hv.datastore.read[url,uuid,datastore,<mode>]',
			'vmware.hv.datastore.size[url,uuid,datastore,<mode>]',
			'vmware.hv.datastore.write[url,uuid,datastore,<mode>]',
			'vmware.hv.discovery[url]',
			'vmware.hv.diskinfo.get[url,uuid]',
			'vmware.hv.fullname[url,uuid]',
			'vmware.hv.hw.cpu.freq[url,uuid]',
			'vmware.hv.hw.cpu.model[url,uuid]',
			'vmware.hv.hw.cpu.num[url,uuid]',
			'vmware.hv.hw.cpu.threads[url,uuid]',
			'vmware.hv.hw.memory[url,uuid]',
			'vmware.hv.hw.model[url,uuid]',
			'vmware.hv.hw.sensors.get[url,uuid]',
			'vmware.hv.hw.serialnumber[url,uuid]',
			'vmware.hv.hw.uuid[url,uuid]',
			'vmware.hv.hw.vendor[url,uuid]',
			'vmware.hv.maintenance[url,uuid]',
			'vmware.hv.memory.size.ballooned[url,uuid]',
			'vmware.hv.memory.used[url,uuid]',
			'vmware.hv.net.if.discovery[url,uuid]',
			'vmware.hv.network.linkspeed[url,uuid,ifname]',
			'vmware.hv.network.in[url,uuid,<mode>]',
			'vmware.hv.network.out[url,uuid,<mode>]',
			'vmware.hv.perfcounter[url,uuid,path,<instance>]',
			'vmware.hv.power[url,uuid,<max>]',
			'vmware.hv.property[url,uuid,prop]',
			'vmware.hv.sensor.health.state[url,uuid]',
			'vmware.hv.tags.get[url,uuid]',
			'vmware.hv.sensors.get[url,uuid]',
			'vmware.hv.status[url,uuid]',
			'vmware.hv.uptime[url,uuid]',
			'vmware.hv.version[url,uuid]',
			'vmware.hv.vm.num[url,uuid]',
			'vmware.rp.cpu.usage[url,rpid]',
			'vmware.rp.memory[url,rpid,<mode>]',
			'vmware.version[url]',
			'vmware.vm.alarms.get[url,uuid]',
			'vmware.vm.attribute[url,uuid,name]',
			'vmware.vm.cluster.name[url,uuid]',
			'vmware.vm.consolidationneeded[url,uuid]',
			'vmware.vm.cpu.latency[url,uuid]',
			'vmware.vm.cpu.num[url,uuid]',
			'vmware.vm.cpu.readiness[url,uuid,<instance>]',
			'vmware.vm.cpu.ready[url,uuid]',
			'vmware.vm.cpu.swapwait[url,uuid,<instance>]',
			'vmware.vm.cpu.usage.perf[url,uuid]',
			'vmware.vm.cpu.usage[url,uuid]',
			'vmware.vm.datacenter.name[url,uuid]',
			'vmware.vm.discovery[url]',
			'vmware.vm.guest.memory.size.swapped[url,uuid]',
			'vmware.vm.guest.osuptime[url,uuid]',
			'vmware.vm.hv.name[url,uuid]',
			'vmware.vm.memory.size.ballooned[url,uuid]',
			'vmware.vm.memory.size.compressed[url,uuid]',
			'vmware.vm.memory.size.consumed[url,uuid]',
			'vmware.vm.memory.size.private[url,uuid]',
			'vmware.vm.memory.size.shared[url,uuid]',
			'vmware.vm.memory.size.swapped[url,uuid]',
			'vmware.vm.memory.size.usage.guest[url,uuid]',
			'vmware.vm.memory.size.usage.host[url,uuid]',
			'vmware.vm.memory.size[url,uuid]',
			'vmware.vm.memory.usage[url,uuid]',
			'vmware.vm.net.if.discovery[url,uuid]',
			'vmware.vm.net.if.in[url,uuid,instance,<mode>]',
			'vmware.vm.net.if.out[url,uuid,instance,<mode>]',
			'vmware.vm.net.if.usage[url,uuid,<instance>]',
			'vmware.vm.perfcounter[url,uuid,path,<instance>]',
			'vmware.vm.powerstate[url,uuid]',
			'vmware.vm.property[url,uuid,prop]',
			'vmware.vm.snapshot.get[url,uuid]',
			'vmware.vm.state[url,uuid]',
			'vmware.vm.storage.committed[url,uuid]',
			'vmware.vm.storage.readoio[url,uuid,instance]',
			'vmware.vm.storage.totalreadlatency[url,uuid,instance]',
			'vmware.vm.storage.totalwritelatency[url,uuid,instance]',
			'vmware.vm.storage.uncommitted[url,uuid]',
			'vmware.vm.storage.unshared[url,uuid]',
			'vmware.vm.storage.writeoio[url,uuid,instance]',
			'vmware.vm.tags.get[url,uuid]',
			'vmware.vm.tools[url,uuid,mode]',
			'vmware.vm.uptime[url,uuid]',
			'vmware.vm.vfs.dev.discovery[url,uuid]',
			'vmware.vm.vfs.dev.read[url,uuid,instance,<mode>]',
			'vmware.vm.vfs.dev.write[url,uuid,instance,<mode>]',
			'vmware.vm.vfs.fs.discovery[url,uuid]',
			'vmware.vm.vfs.fs.size[url,uuid,fsname,<mode>]'
		],
		ITEM_TYPE_SNMPTRAP => [
			'snmptrap.fallback',
			'snmptrap[<regex>]'
		],
		ITEM_TYPE_INTERNAL => [
//			'perseus[boottime]',
//			'perseus[connector_queue]',
//			'perseus[host,,items]',
//			'perseus[host,,items_unsupported]',
//			'perseus[host,,maintenance]',
//			'perseus[host,<type>,available]',
//			'perseus[host,discovery,interfaces]',
//			'perseus[hosts]',
//			'perseus[items]',
//			'perseus[items_unsupported]',
//			'perseus[java,,<param>]',
//			'perseus[lld_queue]',
//			'perseus[preprocessing_queue]',
//			'perseus[process,<type>,<mode>,<state>]',
//			'perseus[proxy,<name>,<param>]',
//			'perseus[proxy,discovery]',
//			'perseus[proxy_history]',
//			'perseus[queue,<from>,<to>]',
//			'perseus[rcache,<cache>,<mode>]',
//			'perseus[requiredperformance]',
//			'perseus[stats,<ip>,<port>,queue,<from>,<to>]',
//			'perseus[stats,<ip>,<port>]',
//			'perseus[tcache, cache, <parameter>]',
//			'perseus[triggers]',
//			'perseus[uptime]',
//			'perseus[vcache,buffer,<mode>]',
//			'perseus[vcache,cache,<parameter>]',
//			'perseus[version]',
//			'perseus[vmware,buffer,<mode>]',
//			'perseus[wcache,<cache>,<mode>]'
		],
		ITEM_TYPE_DB_MONITOR => [
			'db.odbc.discovery[<unique short description>,<dsn>,<connection string>]',
			'db.odbc.get[<unique short description>,<dsn>,<connection string>]',
			'db.odbc.select[<unique short description>,<dsn>,<connection string>]'
		],
		ITEM_TYPE_JMX => [
			'jmx.discovery[<discovery mode>,<object name>,<unique short description>]',
			'jmx.get[<discovery mode>,<object name>,<unique short description>]',
			'jmx[object_name,attribute_name,<unique short description>]'
		],
		ITEM_TYPE_IPMI => [
			'ipmi.get'
		]
	];

	/**
	 * Generates an array used to generate item type lookups in the form: item_type => [key_names].
	 *
	 * @return array
	 */
	public static function getKeysByItemType(): array {
		$keys_by_type = self::KEYS_BY_TYPE;
		$keys_by_type_shortened = [];

		foreach ($keys_by_type as $item_type => $available_keys) {
			$available_keys_shortened = [];

			foreach ($available_keys as $key) {
				$param_start_pos = strpos($key, '[');

				if ($param_start_pos !== false) {
					$key = substr($key, 0, $param_start_pos);
				}

				if (!array_key_exists($key, $available_keys_shortened)) {
					$available_keys_shortened[] = $key;
				}
			}

			$keys_by_type_shortened[$item_type] = $available_keys_shortened;
		}

		return $keys_by_type_shortened;
	}

	/**
	 * Returns items available for the given item type as an array of key => details.
	 *
	 * @param int $type  ITEM_TYPE_PERSEUS, ITEM_TYPE_INTERNAL, etc.
	 *
	 * @return array
	 */
	public static function getByType(int $type): array {
		return array_intersect_key(self::get(), array_flip(self::KEYS_BY_TYPE[$type]));
	}

	/**
	 * Generates an array used to generate item type of information lookups in the form: key_name => value_type.
	 * Value type set to null if key return type varies based on parameters.
	 *
	 * @return array
	 */
	public static function getValueTypeByKey(): array {
		$type_suggestions = [];
		$keys = self::get();

		foreach ($keys as $key => $details) {
			$value_type = $details['value_type'];
			$param_start_pos = strpos($key, '[');

			if ($param_start_pos !== false) {
				$key = substr($key, 0, $param_start_pos);
			}

			if (!array_key_exists($key, $type_suggestions)) {
				$type_suggestions[$key] = $value_type;
			}
			elseif ($type_suggestions[$key] != $value_type) {
				// In case of Key name repeats with different types (f.e. perseus[..]), reset to 'unknown'.
				$type_suggestions[$key] = null;
			}
		}

		return $type_suggestions;
	}

	/**
	 * Returns sets of elements (DOM IDs, default field values, dependent option values) to set visible,
	 * disabled, or set value of, when a 'parent' field value is changed.
	 *
	 * @param bool $data['is_discovery_rule']  Determines default value for ITEM_TYPE_DB_MONITOR Key field.
	 *
	 * @return array
	 */
	public static function fieldSwitchingConfiguration(array $data): array {
		return [
			// Ids to toggle when the field 'type' is changed.
			'for_type' => [
				ITEM_TYPE_CALCULATED => [
					'js-item-formula-label',
					'js-item-formula-field',
					'js-item-delay-label',
					'js-item-delay-field',
					'delay',
					'js-item-flex-intervals-label',
					'js-item-flex-intervals-field',
					['id' => 'key', 'defaultValue' => ''],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_DB_MONITOR => [
					'js-item-delay-label',
					'js-item-delay-field',
					'delay',
					'js-item-flex-intervals-label',
					'js-item-flex-intervals-field',
					'js-item-username-label',
					'js-item-username-field',
					'username',
					'js-item-password-label',
					'js-item-password-field',
					'password',
					'js-item-sql-query-label',
					'js-item-sql-query-field',
					['id' => 'key', 'defaultValue' => $data['is_discovery_rule']
						? PRS_DEFAULT_KEY_DB_MONITOR_DISCOVERY
						: PRS_DEFAULT_KEY_DB_MONITOR
					],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_DEPENDENT => [
					'js-item-master-item-label',
					'js-item-master-item-field',
					['id' => 'key', 'defaultValue' => ''],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_EXTERNAL => [
					'js-item-interface-label',
					'js-item-interface-field',
					'interfaceid',
					'js-item-delay-label',
					'js-item-delay-field',
					'delay',
					'js-item-flex-intervals-label',
					'js-item-flex-intervals-field',
					['id' => 'key', 'defaultValue' => ''],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_HTTPAGENT => [
					'js-item-url-label',
					'js-item-url-field',
					'js-item-delay-label',
					'js-item-delay-field',
					'delay',
					'js-item-flex-intervals-label',
					'js-item-flex-intervals-field',
					'js-item-query-fields-label',
					'js-item-query-fields-field',
					'js-item-request-method-label',
					'js-item-request-method-field',
					'request_method',
					'js-item-timeout-label',
					'js-item-timeout-field',
					'js-item-post-type-label',
					'js-item-post-type-field',
					'js-item-posts-label',
					'js-item-posts-field',
					'js-item-headers-label',
					'js-item-headers-field',
					'js-item-status-codes-label',
					'js-item-status-codes-field',
					'js-item-follow-redirects-label',
					'js-item-follow-redirects-field',
					'js-item-http-proxy-label',
					'js-item-http-proxy-field',
					'js-item-http-authtype-label',
					'js-item-http-authtype-field',
					'http_authtype',
					'js-item-retrieve-mode-label',
					'js-item-retrieve-mode-field',
					'js-item-output-format-label',
					'js-item-output-format-field',
					'js-item-verify-peer-label',
					'js-item-verify-peer-field',
					'js-item-verify-host-label',
					'js-item-verify-host-field',
					'js-item-ssl-cert-file-label',
					'js-item-ssl-cert-file-field',
					'js-item-ssl-key-file-label',
					'js-item-ssl-key-file-field',
					'js-item-ssl-key-password-label',
					'js-item-ssl-key-password-field',
					'js-item-interface-label',
					'js-item-interface-field',
					'interfaceid',
					'js-item-allow-traps-label',
					'js-item-allow-traps-field',
					'allow_traps',
					'trapper_hosts',
					['id' => 'key', 'defaultValue' => ''],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_INTERNAL => [
					'js-item-delay-label',
					'js-item-delay-field',
					'delay',
					'js-item-flex-intervals-label',
					'js-item-flex-intervals-field',
					['id' => 'key', 'defaultValue' => ''],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_IPMI => [
					'js-item-interface-label',
					'js-item-interface-field',
					'interfaceid',
					'js-item-impi-sensor-label',
					'js-item-impi-sensor-field',
					'ipmi_sensor',
					'js-item-delay-label',
					'js-item-delay-field',
					'delay',
					'js-item-flex-intervals-label',
					'js-item-flex-intervals-field',
					['id' => 'key', 'defaultValue' => ''],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_JMX => [
					'js-item-interface-label',
					'js-item-interface-field',
					'interfaceid',
					'js-item-jmx-endpoint-label',
					'js-item-jmx-endpoint-field',
					'js-item-username-label',
					'js-item-username-field',
					'username',
					'jmx_endpoint',
					'js-item-password-label',
					'js-item-password-field',
					'password',
					'js-item-delay-label',
					'js-item-delay-field',
					'delay',
					'js-item-flex-intervals-label',
					'js-item-flex-intervals-field',
					['id' => 'key', 'defaultValue' => ''],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_SCRIPT => [
					'js-item-parameters-label',
					'js-item-parameters-field',
					'js-item-script-label',
					'js-item-script-field',
					'js-item-timeout-label',
					'js-item-timeout-field',
					'js-item-delay-label',
					'js-item-delay-field',
					'delay',
					'js-item-flex-intervals-label',
					'js-item-flex-intervals-field',
					['id' => 'key', 'defaultValue' => ''],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_SIMPLE => [
					'js-item-delay-label',
					'js-item-delay-field',
					'delay',
					'js-item-flex-intervals-label',
					'js-item-flex-intervals-field',
					'js-item-interface-label',
					'js-item-interface-field',
					'interfaceid',
					'js-item-username-label',
					'js-item-username-field',
					'username',
					'js-item-password-label',
					'js-item-password-field',
					'password',
					['id' => 'key', 'defaultValue' => ''],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_SNMP => [
					'js-item-interface-label',
					'js-item-interface-field',
					'interfaceid',
					'js-item-snmp-oid-label',
					'js-item-snmp-oid-field',
					'snmp_oid',
					'js-item-delay-label',
					'js-item-delay-field',
					'delay',
					'js-item-flex-intervals-label',
					'js-item-flex-intervals-field',
					['id' => 'key', 'defaultValue' => ''],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_SNMPTRAP => [
					'js-item-interface-label',
					'js-item-interface-field',
					'interfaceid',
					['id' => 'key', 'defaultValue' => ''],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_SSH => [
					'js-item-interface-label',
					'js-item-interface-field',
					'interfaceid',
					'js-item-authtype-label',
					'js-item-authtype-field',
					'authtype',
					'js-item-username-label',
					'js-item-username-field',
					'username',
					'js-item-password-label',
					'js-item-password-field',
					'password',
					'js-item-executed-script-label',
					'js-item-executed-script-field',
					'js-item-delay-label',
					'js-item-delay-field',
					'delay',
					'js-item-flex-intervals-label',
					'js-item-flex-intervals-field',
					'params_script',
					['id' => 'key', 'defaultValue' => PRS_DEFAULT_KEY_SSH],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_TELNET => [
					'js-item-interface-label',
					'js-item-interface-field',
					'interfaceid',
					'js-item-username-label',
					'js-item-username-field',
					'username',
					'js-item-password-label',
					'js-item-password-field',
					'password',
					'js-item-executed-script-label',
					'js-item-executed-script-field',
					'js-item-delay-label',
					'js-item-delay-field',
					'delay',
					'js-item-flex-intervals-label',
					'js-item-flex-intervals-field',
					'params_script',
					['id' => 'key', 'defaultValue' => PRS_DEFAULT_KEY_TELNET],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_TRAPPER => [
					'js-item-trapper-hosts-label',
					'js-item-trapper-hosts-field',
					'trapper_hosts',
					['id' => 'key', 'defaultValue' => ''],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_PERSEUS => [
					'js-item-interface-label',
					'js-item-interface-field',
					'interfaceid',
					'js-item-delay-label',
					'js-item-delay-field',
					'delay',
					'js-item-flex-intervals-label',
					'js-item-flex-intervals-field',
					['id' => 'key', 'defaultValue' => ''],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_PERSEUS_ACTIVE => [
					'js-item-delay-label',
					'js-item-delay-field',
					'delay',
					'js-item-flex-intervals-label',
					'js-item-flex-intervals-field',
					['id' => 'key', 'defaultValue' => ''],
					['id' => 'value_type', 'defaultValue' => '']
				]
			],
			// Ids to toggle when the field 'authtype' is changed.
			'for_authtype' => [
				ITEM_AUTHTYPE_PUBLICKEY => [
					'js-item-private-key-label',
					'js-item-private-key-field',
					'privatekey',
					'js-item-public-key-label',
					'js-item-public-key-field',
					'publickey'
				]
			],
			'for_http_auth_type' => [
				PRS_HTTP_AUTH_BASIC => [
					'js-item-http-username-label',
					'js-item-http-username-field',
					'js-item-http-password-label',
					'js-item-http-password-field'
				],
				PRS_HTTP_AUTH_NTLM => [
					'js-item-http-username-label',
					'js-item-http-username-field',
					'js-item-http-password-label',
					'js-item-http-password-field'
				],
				PRS_HTTP_AUTH_KERBEROS => [
					'js-item-http-username-label',
					'js-item-http-username-field',
					'js-item-http-password-label',
					'js-item-http-password-field'
				],
				PRS_HTTP_AUTH_DIGEST => [
					'js-item-http-username-label',
					'js-item-http-username-field',
					'js-item-http-password-label',
					'js-item-http-password-field'
				]
			],
			'for_traps' => [
				HTTPCHECK_ALLOW_TRAPS_ON => [
					'js-item-trapper-hosts-label',
					'js-item-trapper-hosts-field'
				]
			],
			'for_value_type' => [
				ITEM_VALUE_TYPE_FLOAT => [
					'js-item-inventory-link-label',
					'js-item-inventory-link-field',
					'inventory_link',
					'js-item-trends-label',
					'js-item-trends-field',
					'js-item-units-label',
					'js-item-units-field',
					'units',
					'js-item-value-map-label',
					'js-item-value-map-field',
					'valuemap_name',
					'valuemapid'
				],
				ITEM_VALUE_TYPE_LOG => [
					'js-item-log-time-format-label',
					'js-item-log-time-format-field',
					'logtimefmt'
				],
				ITEM_VALUE_TYPE_STR => [
					'js-item-inventory-link-label',
					'js-item-inventory-link-field',
					'inventory_link',
					'js-item-value-map-label',
					'js-item-value-map-field',
					'valuemap_name',
					'valuemapid'
				],
				ITEM_VALUE_TYPE_TEXT => [
					'js-item-inventory-link-label',
					'js-item-inventory-link-field',
					'inventory_link'
				],
				ITEM_VALUE_TYPE_UINT64 => [
					'js-item-inventory-link-label',
					'js-item-inventory-link-field',
					'inventory_link',
					'js-item-trends-label',
					'js-item-trends-field',
					'js-item-units-label',
					'js-item-units-field',
					'units',
					'js-item-value-map-label',
					'js-item-value-map-field',
					'valuemap_name',
					'valuemapid'
				]
			]
		];
	}

	private static function get(): array {
		return [
			'agent.hostmetadata' => [
				'description' => t('zapi', 'Agent host metadata. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'agent.hostname' => [
				'description' => t('zapi', 'Agent host name. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'agent.ping' => [
				'description' => t('zapi', 'Agent availability check. Returns nothing - unavailable; 1 - available'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'agent.variant' => [
				'description' => t('zapi', 'Agent variant check. Returns 1 - for Perseus agent; 2 - for Perseus agent 2'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'agent.version' => [
				'description' => t('zapi', 'Version of Perseus agent. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'db.odbc.discovery[<unique short description>,<dsn>,<connection string>]' => [
				'description' => t('zapi', 'Transform SQL query result into a JSON array for low-level discovery.'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'db.odbc.get[<unique short description>,<dsn>,<connection string>]' => [
				'description' => t('zapi', 'Transform SQL query result into a JSON array.'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'db.odbc.select[<unique short description>,<dsn>,<connection string>]' => [
				'description' => t('zapi', 'Return first column of the first row of the SQL query result.'),
				'value_type' => null
			],
			'eventlog[name,<regexp>,<severity>,<source>,<eventid>,<maxlines>,<mode>]' => [
				'description' => t('zapi', 'Event log monitoring. Returns log'),
				'value_type' => ITEM_VALUE_TYPE_LOG
			],
			'icmpping[<target>,<packets>,<interval>,<size>,<timeout>]' => [
				'description' => t('zapi', 'Checks if host is accessible by ICMP ping. 0 - ICMP ping fails. 1 - ICMP ping successful.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'icmppingloss[<target>,<packets>,<interval>,<size>,<timeout>]' => [
				'description' => t('zapi', 'Returns percentage of lost ICMP ping packets.'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'icmppingsec[<target>,<packets>,<interval>,<size>,<timeout>,<mode>]' => [
				'description' => t('zapi', 'Returns ICMP ping response time in seconds. Example: 0.02'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'ipmi.get' => [
				'description' => t('zapi', 'IPMI sensor IDs and other sensor-related parameters. Returns JSON.'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'jmx.discovery[<discovery mode>,<object name>,<unique short description>]' => [
				'description' => t('zapi', 'Return a JSON array with LLD macros describing the MBean objects or their attributes. Can be used for LLD.'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'jmx.get[<discovery mode>,<object name>,<unique short description>]' => [
				'description' => t('zapi', 'Return a JSON array with MBean objects or their attributes. Compared to jmx.discovery it does not define LLD macros. Can be used for LLD.'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'jmx[object_name,attribute_name,<unique short description>]' => [
				'description' => t('zapi', 'Return value of an attribute of MBean object.'),
				'value_type' => null
			],
			'kernel.maxfiles' => [
				'description' => t('zapi', 'Maximum number of opened files supported by OS. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'kernel.maxproc' => [
				'description' => t('zapi', 'Maximum number of processes supported by OS. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'kernel.openfiles' => [
				'description' => t('zapi', 'Number of currently open file descriptors. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'log.count[file,<regexp>,<encoding>,<maxproclines>,<mode>,<maxdelay>,<options>,<persistent_dir>]' => [
				'description' => t('zapi', 'Count of matched lines in log file monitoring. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'log[file,<regexp>,<encoding>,<maxlines>,<mode>,<output>,<maxdelay>,<options>,<persistent_dir>]' => [
				'description' => t('zapi', 'Log file monitoring. Returns log'),
				'value_type' => ITEM_VALUE_TYPE_LOG
			],
			'logrt.count[file_regexp,<regexp>,<encoding>,<maxproclines>,<mode>,<maxdelay>,<options>,<persistent_dir>]' => [
				'description' => t('zapi', 'Count of matched lines in log file monitoring with log rotation support. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'logrt[file_regexp,<regexp>,<encoding>,<maxlines>,<mode>,<output>,<maxdelay>,<options>,<persistent_dir>]' => [
				'description' => t('zapi', 'Log file monitoring with log rotation support. Returns log'),
				'value_type' => ITEM_VALUE_TYPE_LOG
			],
			'modbus.get[endpoint,<slaveid>,<function>,<address>,<count>,<type>,<endianness>,<offset>]' => [
				'description' => t('zapi', 'Reads modbus data. Returns various types'),
				'value_type' => null
			],
			'mqtt.get[<broker_url>,topic,<username>,<password>]' => [
				'description' => t('zapi', 'Value of MQTT topic. Format of returned data depends on the topic content. If wildcards are used, returns topic values in JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'net.dns.record[<ip>,name,<type>,<timeout>,<count>,<protocol>]' => [
				'description' => t('zapi', 'Performs a DNS query. Returns character string with the required type of information'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'net.dns[<ip>,name,<type>,<timeout>,<count>,<protocol>]' => [
				'description' => t('zapi', 'Checks if DNS service is up. Returns 0 - DNS is down (server did not respond or DNS resolution failed); 1 - DNS is up'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'net.if.collisions[if]' => [
				'description' => t('zapi', 'Number of out-of-window collisions. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'net.if.discovery' => [
				'description' => t('zapi', 'List of network interfaces. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'net.if.in[if,<mode>]' => [
				'description' => t('zapi', 'Incoming traffic statistics on network interface. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'net.if.list' => [
				'description' => t('zapi', 'Network interface list (includes interface type, status, IPv4 address, description). Returns text'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'net.if.out[if,<mode>]' => [
				'description' => t('zapi', 'Outgoing traffic statistics on network interface. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'net.if.total[if,<mode>]' => [
				'description' => t('zapi', 'Sum of incoming and outgoing traffic statistics on network interface. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'net.tcp.listen[port]' => [
				'description' => t('zapi', 'Checks if this TCP port is in LISTEN state. Returns 0 - it is not in LISTEN state; 1 - it is in LISTEN state'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'net.tcp.port[<ip>,port]' => [
				'description' => t('zapi', 'Checks if it is possible to make TCP connection to specified port. Returns 0 - cannot connect; 1 - can connect'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'net.tcp.service.perf[service,<ip>,<port>]' => [
				'description' => t('zapi', 'Checks performance of TCP service. Returns 0 - service is down; seconds - the number of seconds spent while connecting to the service'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'net.tcp.service[service,<ip>,<port>]' => [
				'description' => t('zapi', 'Checks if service is running and accepting TCP connections. Returns 0 - service is down; 1 - service is running'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'net.tcp.socket.count[<laddr>,<lport>,<raddr>,<rport>,<state>]' => [
				'description' => t('zapi', 'Returns number of TCP sockets that match parameters. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'net.udp.listen[port]' => [
				'description' => t('zapi', 'Checks if this UDP port is in LISTEN state. Returns 0 - it is not in LISTEN state; 1 - it is in LISTEN state'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'net.udp.service.perf[service,<ip>,<port>]' => [
				'description' => t('zapi', 'Checks performance of UDP service. Returns 0 - service is down; seconds - the number of seconds spent waiting for response from the service'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'net.udp.service[service,<ip>,<port>]' => [
				'description' => t('zapi', 'Checks if service is running and responding to UDP requests. Returns 0 - service is down; 1 - service is running'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'net.udp.socket.count[<laddr>,<lport>,<raddr>,<rport>,<state>]' => [
				'description' => t('zapi', 'Returns number of UDP sockets that match parameters. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'perf_counter[counter,<interval>]' => [
				'description' => t('zapi', 'Value of any Windows performance counter. Returns integer, float, string or text (depending on the request)'),
				'value_type' => null
			],
			'perf_counter_en[counter,<interval>]' => [
				'description' => t('zapi', 'Value of any Windows performance counter in English. Returns integer, float, string or text (depending on the request)'),
				'value_type' => null
			],
			'perf_instance.discovery[object]' => [
				'description' => t('zapi', 'List of object instances of Windows performance counters. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'perf_instance_en.discovery[object]' => [
				'description' => t('zapi', 'List of object instances of Windows performance counters, discovered using object names in English. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'proc.cpu.util[<name>,<user>,<type>,<cmdline>,<mode>,<zone>]' => [
				'description' => t('zapi', 'Process CPU utilization percentage. Returns float'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'proc.get[<name>,<user>,<cmdline>,<mode>]' => [
				'description' => t('zapi', 'List of OS processes with attributes. Returns JSON array'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'proc.mem[<name>,<user>,<mode>,<cmdline>,<memtype>]' => [
				'description' => t('zapi', 'Memory used by process in bytes. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'proc.num[<name>,<user>,<state>,<cmdline>,<zone>]' => [
				'description' => t('zapi', 'The number of processes. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'proc_info[process,<attribute>,<type>]' => [
				'description' => t('zapi', 'Various information about specific process(es). Returns float'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'registry.data[key,<value name>]' => [
				'description' => t('zapi', 'Value data for value name in Windows Registry key.'),
				'value_type' => null
			],
			'registry.get[key,<mode>,<name regexp>]' => [
				'description' => t('zapi', 'List of Windows Registry values or keys located at given key. Returns JSON.'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'sensor[device,sensor,<mode>]' => [
				'description' => t('zapi', 'Hardware sensor reading. Returns float'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'service.info[service,<param>]' => [
				'description' => t('zapi', 'Information about a service. Returns integer with param as state, startup; string - with param as displayname, path, user; text - with param as description; Specifically for state: 0 - running, 1 - paused, 2 - start pending, 3 - pause pending, 4 - continue pending, 5 - stop pending, 6 - stopped, 7 - unknown, 255 - no such service; Specifically for startup: 0 - automatic, 1 - automatic delayed, 2 - manual, 3 - disabled, 4 - unknown'),
				'value_type' => null
			],
			'services[<type>,<state>,<exclude>]' => [
				'description' => t('zapi', 'Listing of services. Returns 0 - if empty; text - list of services separated by a newline'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'snmptrap.fallback' => [
				'description' => t('zapi', 'Catches all SNMP traps that were not caught by any of snmptrap[] items.'),
				'value_type' => null
			],
			'snmptrap[<regex>]' => [
				'description' => t('zapi', 'Catches all SNMP traps that match regex. If regexp is unspecified, catches any trap.'),
				'value_type' => null
			],
			'system.boottime' => [
				'description' => t('zapi', 'System boot time. Returns integer (Unix timestamp)'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'system.cpu.discovery' => [
				'description' => t('zapi', 'List of detected CPUs/CPU cores. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'system.cpu.intr' => [
				'description' => t('zapi', 'Device interrupts. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'system.cpu.load[<cpu>,<mode>]' => [
				'description' => t('zapi', 'CPU load. Returns float'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'system.cpu.num[<type>]' => [
				'description' => t('zapi', 'Number of CPUs. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'system.cpu.switches' => [
				'description' => t('zapi', 'Count of context switches. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'system.cpu.util[<cpu>,<type>,<mode>,<logical_or_physical>]' => [
				'description' => t('zapi', 'CPU utilization percentage. Returns float'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'system.hostname[<type>,<transform>]' => [
				'description' => t('zapi', 'System host name. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'system.hw.chassis[<info>]' => [
				'description' => t('zapi', 'Chassis information. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'system.hw.cpu[<cpu>,<info>]' => [
				'description' => t('zapi', 'CPU information. Returns string or integer'),
				'value_type' => null
			],
			'system.hw.devices[<type>]' => [
				'description' => t('zapi', 'Listing of PCI or USB devices. Returns text'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'system.hw.macaddr[<interface>,<format>]' => [
				'description' => t('zapi', 'Listing of MAC addresses. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'system.localtime[<type>]' => [
				'description' => t('zapi', 'System time. Returns integer with type as UTC; string - with type as local'),
				'value_type' => null
			],
			'system.run[command,<mode>]' => [
				'description' => t('zapi', 'Run specified command on the host. Returns text result of the command; 1 - with mode as nowait (regardless of command result)'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'system.stat[resource,<type>]' => [
				'description' => t('zapi', 'System statistics. Returns integer or float'),
				'value_type' => null
			],
			'system.sw.arch' => [
				'description' => t('zapi', 'Software architecture information. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'system.sw.os[<info>]' => [
				'description' => t('zapi', 'Operating system information. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'system.sw.os.get' => [
				'description' => t('zapi', 'Operating system version information. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'system.sw.packages[<regexp>,<manager>,<format>]' => [
				'description' => t('zapi', 'Listing of installed packages. Returns text'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'system.sw.packages.get[<regexp>,<manager>]' => [
				'description' => t('zapi', 'Detailed listing of installed packages. Returns text in JSON format'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'system.swap.in[<device>,<type>]' => [
				'description' => t('zapi', 'Swap in (from device into memory) statistics. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'system.swap.out[<device>,<type>]' => [
				'description' => t('zapi', 'Swap out (from memory onto device) statistics. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'system.swap.size[<device>,<type>]' => [
				'description' => t('zapi', 'Swap space size in bytes or in percentage from total. Returns integer for bytes; float for percentage'),
				'value_type' => null
			],
			'system.uname' => [
				'description' => t('zapi', 'Identification of the system. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'system.uptime' => [
				'description' => t('zapi', 'System uptime in seconds. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'system.users.num' => [
				'description' => t('zapi', 'Number of users logged in. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vfs.dev.discovery' => [
				'description' => t('zapi', 'List of block devices and their type. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vfs.dev.read[<device>,<type>,<mode>]' => [
				'description' => t('zapi', 'Disk read statistics. Returns integer with type in sectors, operations, bytes; float with type in sps, ops, bps'),
				'value_type' => null
			],
			'vfs.dev.write[<device>,<type>,<mode>]' => [
				'description' => t('zapi', 'Disk write statistics. Returns integer with type in sectors, operations, bytes; float with type in sps, ops, bps'),
				'value_type' => null
			],
			'vfs.dir.count[dir,<regex_incl>,<regex_excl>,<types_incl>,<types_excl>,<max_depth>,<min_size>,<max_size>,<min_age>,<max_age>,<regex_excl_dir>]' => [
				'description' => t('zapi', 'Count of directory entries, recursively. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vfs.dir.get[dir,<regex_incl>,<regex_excl>,<types_incl>,<types_excl>,<max_depth>,<min_size>,<max_size>,<min_age>,<max_age>,<regex_excl_dir>]' => [
				'description' => t('zapi', 'List of directory entries, recursively. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vfs.dir.size[dir,<regex_incl>,<regex_excl>,<mode>,<max_depth>,<regex_excl_dir>]' => [
				'description' => t('zapi', 'Directory size (in bytes). Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vfs.file.cksum[file,<mode>]' => [
				'description' => t('zapi', 'File checksum, calculated by the UNIX cksum algorithm. Returns integer for crc32 (default) and string for md5, sha256'),
				'value_type' => null
			],
			'vfs.file.contents[file,<encoding>]' => [
				'description' => t('zapi', 'Retrieving contents of a file. Returns text'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vfs.file.exists[file,<types_incl>,<types_excl>]' => [
				'description' => t('zapi', 'Checks if file exists. Returns 0 - not found; 1 - file of the specified type exists'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vfs.file.get[file]' => [
				'description' => t('zapi', 'Information about a file. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vfs.file.md5sum[file]' => [
				'description' => t('zapi', 'MD5 checksum of file. Returns character string (MD5 hash of the file)'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vfs.file.owner[file,<ownertype>,<resulttype>]' => [
				'description' => t('zapi', 'File owner information. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vfs.file.permissions[file]' => [
				'description' => t('zapi', 'Returns 4-digit string containing octal number with Unix permissions'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vfs.file.regexp[file,regexp,<encoding>,<start line>,<end line>,<output>]' => [
				'description' => t('zapi', 'Find string in a file. Returns the line containing the matched string, or as specified by the optional output parameter'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vfs.file.regmatch[file,regexp,<encoding>,<start line>,<end line>]' => [
				'description' => t('zapi', 'Find string in a file. Returns 0 - match not found; 1 - found'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vfs.file.size[file,<mode>]' => [
				'description' => t('zapi', 'File size in bytes (default) or in newlines. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vfs.file.time[file,<mode>]' => [
				'description' => t('zapi', 'File time information. Returns integer (Unix timestamp)'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vfs.fs.discovery' => [
				'description' => t('zapi', 'List of mounted filesystems and their types. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vfs.fs.get' => [
				'description' => t('zapi', 'List of mounted filesystems, their types, disk space and inode statistics. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vfs.fs.inode[fs,<mode>]' => [
				'description' => t('zapi', 'Number or percentage of inodes. Returns integer for number; float for percentage'),
				'value_type' => null
			],
			'vfs.fs.size[fs,<mode>]' => [
				'description' => t('zapi', 'Disk space in bytes or in percentage from total. Returns integer for bytes; float for percentage'),
				'value_type' => null
			],
			'vm.memory.size[<mode>]' => [
				'description' => t('zapi', 'Memory size in bytes or in percentage from total. Returns integer for bytes; float for percentage'),
				'value_type' => null
			],
			'vm.vmemory.size[<type>]' => [
				'description' => t('zapi', 'Virtual space size in bytes or in percentage from total. Returns integer for bytes; float for percentage'),
				'value_type' => null
			],
			'vmware.alarms.get[url]' => [
				'description' => t('zapi', 'VMware virtual center alarms data, returns JSON, "url" - VMware service URL'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.cl.perfcounter[url,id,path,<instance>]' => [
				'description' => t('zapi', 'VMware cluster performance counter, "url" - VMware service URL, "id" - VMware cluster id, "path" - performance counter path, "instance" - performance counter instance'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'vmware.cluster.alarms.get[url,id]' => [
				'description' => t('zapi', 'VMware cluster alarms data, returns JSON, "url" - VMware service URL, "id" - VMware cluster id'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.cluster.discovery[url]' => [
				'description' => t('zapi', 'Discovery of VMware clusters, "url" - VMware service URL. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.cluster.property[url,id,prop]' => [
				'description' => t('zapi', 'VMware cluster property, "url" - VMware service URL, "id" - VMware cluster id, "prop" - property path'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.cluster.status[url,name]' => [
				'description' => t('zapi', 'VMware cluster status, "url" - VMware service URL, "name" - VMware cluster name'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.cluster.tags.get[url,id]' => [
				'description' => t('zapi', 'VMware cluster tags array, "url" - VMware service URL, "id" - VMware cluster id'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.datastore.alarms.get[url,uuid]' => [
				'description' => t('zapi', 'VMware datastore alarms data, returns JSON, "url" - VMware service URL, "uuid" - VMware datastore global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.datastore.discovery[url]' => [
				'description' => t('zapi', 'Discovery of VMware datastores, "url" - VMware service URL. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.datastore.hv.list[url,datastore]' => [
				'description' => t('zapi', 'VMware datastore hypervisors list, "url" - VMware service URL, "datastore" - datastore name'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.datastore.perfcounter[url,uuid,path,<instance>]' => [
				'description' => t('zapi', 'VMware datastore performance counter, "url" - VMware service URL, "uuid" - VMware datastore global unique identifier, "path" - performance counter path, "instance" - datastore perfcounter instance from vmware.hv.diskinfo.get'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'vmware.datastore.property[url,uuid,prop]' => [
				'description' => t('zapi', 'VMware datastore property, "url" - VMware service URL, "uuid" - VMware datastore global unique identifier, "prop" - property path'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.datastore.read[url,datastore,<mode>]' => [
				'description' => t('zapi', 'VMware datastore read statistics, "url" - VMware service URL, "datastore" - datastore name, "mode"- latency/maxlatency - average or maximum'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.datastore.size[url,datastore,<mode>]' => [
				'description' => t('zapi', 'VMware datastore capacity statistics in bytes or in percentage from total. Returns integer for bytes; float for percentage'),
				'value_type' => null
			],
			'vmware.datastore.tags.get[url,uuid]' => [
				'description' => t('zapi', 'VMware datastore tags array, "url" - VMware service URL, "uuid" - VMware datastore global unique identifier. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.datastore.write[url,datastore,<mode>]' => [
				'description' => t('zapi', 'VMware datastore write statistics, "url" - VMware service URL, "datastore" - datastore name, "mode"- latency/maxlatency - average or maximum'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.dc.alarms.get[url,id]' => [
				'description' => t('zapi', 'VMware datacenter alarms data, returns JSON, "url" - VMware service URL, "id" - VMware datacenter id'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.dc.discovery[url]' => [
				'description' => t('zapi', 'VMware datacenters and their IDs, "url" - VMware service URL. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.dc.tags.get[url,id]' => [
				'description' => t('zapi', 'VMware datacenter tags array, "url" - VMware service URL, "id" - VMware datacenter id. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.dvswitch.discovery[url]' => [
				'description' => t('zapi', 'VMware Distributed Virtual Switch, "url" - VMware service URL. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.dvswitch.fetchports.get[url,uuid,<filter>,<mode>]' => [
				'description' => t('zapi', 'VMware FetchDVPorts wrapper, "url" - VMware service URL, "uuid" - VMware DVSwitch global unique identifier, "filter" - vmware data object DistributedVirtualSwitchPortCriteria, "mode"- state(default)/full. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.eventlog[url,<mode>]' => [
				'description' => t('zapi', 'VMware event log, "url" - VMware service URL, "mode"- all (default), skip - skip processing of older data'),
				'value_type' => ITEM_VALUE_TYPE_LOG
			],
			'vmware.fullname[url]' => [
				'description' => t('zapi', 'VMware service full name, "url" - VMware service URL'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.hv.alarms.get[url,uuid]' => [
				'description' => t('zapi', 'VMware hypervisor alarms data, returns JSON, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.hv.cluster.name[url,uuid]' => [
				'description' => t('zapi', 'VMware hypervisor cluster name, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.hv.connectionstate[url,uuid]' => [
				'description' => t('zapi', 'VMware hypervisor connection state, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.hv.cpu.usage.perf[url,uuid]' => [
				'description' => t('zapi', 'CPU usage as a percentage during the interval, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'vmware.hv.cpu.usage[url,uuid]' => [
				'description' => t('zapi', 'VMware hypervisor processor usage in Hz, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.hv.cpu.utilization[url,uuid]' => [
				'description' => t('zapi', 'CPU usage as a percentage during the interval depends on power management or HT, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'vmware.hv.datacenter.name[url,uuid]' => [
				'description' => t('zapi', 'VMware hypervisor datacenter name, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.hv.datastore.discovery[url,uuid]' => [
				'description' => t('zapi', 'Discovery of VMware hypervisor datastores, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.hv.datastore.list[url,uuid]' => [
				'description' => t('zapi', 'VMware hypervisor datastores list, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.hv.datastore.multipath[url,uuid,<datastore>,<partitionid>]' => [
				'description' => t('zapi', 'Number of available DS paths, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier, "datastore" - Datastore name, "partitionid" - internal id of physical device from vmware.hv.datastore.discovery'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.hv.datastore.read[url,uuid,datastore,<mode>]' => [
				'description' => t('zapi', 'VMware hypervisor datastore read statistics, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier, "datastore" - datastore name, "mode"- latency'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.hv.datastore.size[url,uuid,datastore,<mode>]' => [
				'description' => t('zapi', 'VMware datastore capacity statistics in bytes or in percentage from total, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier, "datastore" - datastore name, "mode" - total(default)/free/pfree/uncommitted. Returns integer for bytes; float for percentage'),
				'value_type' => null
			],
			'vmware.hv.datastore.write[url,uuid,datastore,<mode>]' => [
				'description' => t('zapi', 'VMware hypervisor datastore write statistics, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier, "datastore" - datastore name, "mode"- latency'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.hv.discovery[url]' => [
				'description' => t('zapi', 'Discovery of VMware hypervisors, "url" - VMware service URL. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.hv.diskinfo.get[url,uuid]' => [
				'description' => t('zapi', 'Info about internal disks of hypervisor required for vmware.datastore.perfcounter, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.hv.fullname[url,uuid]' => [
				'description' => t('zapi', 'VMware hypervisor name, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.hv.hw.cpu.freq[url,uuid]' => [
				'description' => t('zapi', 'VMware hypervisor processor frequency, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'vmware.hv.hw.cpu.model[url,uuid]' => [
				'description' => t('zapi', 'VMware hypervisor processor model, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.hv.hw.cpu.num[url,uuid]' => [
				'description' => t('zapi', 'Number of processor cores on VMware hypervisor, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.hv.hw.cpu.threads[url,uuid]' => [
				'description' => t('zapi', 'Number of processor threads on VMware hypervisor, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.hv.hw.memory[url,uuid]' => [
				'description' => t('zapi', 'VMware hypervisor total memory size, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.hv.hw.model[url,uuid]' => [
				'description' => t('zapi', 'VMware hypervisor model, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.hv.hw.sensors.get[url,uuid]' => [
				'description' => t('zapi', 'VMware hypervisor sensors value, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.hv.hw.serialnumber[url,uuid]' => [
				'description' => t('zapi', 'VMware hypervisor serialnumber, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.hv.hw.uuid[url,uuid]' => [
				'description' => t('zapi', 'VMware hypervisor BIOS UUID, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.hv.hw.vendor[url,uuid]' => [
				'description' => t('zapi', 'VMware hypervisor vendor name, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.hv.maintenance[url,uuid]' => [
				'description' => t('zapi', 'VVMware hypervisor maintenance status, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier. Returns 0 - not in maintenance; 1 - in maintenance'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.hv.memory.size.ballooned[url,uuid]' => [
				'description' => t('zapi', 'VMware hypervisor ballooned memory size, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.hv.memory.used[url,uuid]' => [
				'description' => t('zapi', 'VMware hypervisor used memory size, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.hv.net.if.discovery[url,uuid]' => [
				'description' => t('zapi', 'Discovery of VMware hypervisor network interfaces, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.hv.network.in[url,uuid,<mode>]' => [
				'description' => t('zapi', 'VMware hypervisor network input statistics, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier, "mode"- bps'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.hv.network.linkspeed[url,uuid,ifname]' => [
				'description' => t('zapi', 'VMware hypervisor network interface speed, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier, <ifname> - interface name'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.hv.network.out[url,uuid,<mode>]' => [
				'description' => t('zapi', 'VMware hypervisor network output statistics, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier, "mode"- bps'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.hv.perfcounter[url,uuid,path,<instance>]' => [
				'description' => t('zapi', 'VMware hypervisor performance counter, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier, "path" - performance counter path, "instance" - performance counter instance'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'vmware.hv.power[url,uuid,<max>]' => [
				'description' => t('zapi', 'Power usage , "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier, "max" - Maximum allowed power usage'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'vmware.hv.property[url,uuid,prop]' => [
				'description' => t('zapi', 'VMware hypervisor property , "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier, "prop" - property path'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.hv.sensor.health.state[url,uuid]' => [
				'description' => t('zapi', 'VMware hypervisor health state rollup sensor, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier. Returns 0 - gray; 1 - green; 2 - yellow; 3 - red'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.hv.sensors.get[url,uuid]' => [
				'description' => t('zapi', 'VMware hypervisor HW vendor state sensors, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.hv.status[url,uuid]' => [
				'description' => t('zapi', 'VMware hypervisor status, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => null
			],
			'vmware.hv.tags.get[url,uuid]' => [
				'description' => t('zapi', 'VMware hypervisor tags array, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.hv.uptime[url,uuid]' => [
				'description' => t('zapi', 'VMware hypervisor uptime, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.hv.version[url,uuid]' => [
				'description' => t('zapi', 'VMware hypervisor version, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.hv.vm.num[url,uuid]' => [
				'description' => t('zapi', 'Number of virtual machines on VMware hypervisor, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.rp.cpu.usage[url,rpid]' => [
				'description' => t('zapi', 'CPU usage in hertz during the interval on VMware Resource Pool, "url" - VMware service URL, "rpid" - VMware resource pool id'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.rp.memory[url,rpid,<mode>]' => [
				'description' => t('zapi', 'Memory metrics of VMware Resource Pool, "url" - VMware service URL, "rpid" - VMware resource pool id, "mode"- consumed(default)/ballooned/overhead memory'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.version[url]' => [
				'description' => t('zapi', 'VMware service version, "url" - VMware service URL'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.vm.alarms.get[url,uuid]' => [
				'description' => t('zapi', 'VMware virtual machine alarms data, returns JSON, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.vm.attribute[url,uuid,name]' => [
				'description' => t('zapi', 'VMware virtual machine custom attribute value, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier, "name" - custom attribute name'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.vm.cluster.name[url,uuid]' => [
				'description' => t('zapi', 'VMware virtual machine name, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.vm.consolidationneeded[url,uuid]' => [
				'description' => t('zapi', 'VMware virtual machine disk requires consolidation, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.vm.cpu.latency[url,uuid]' => [
				'description' => t('zapi', 'Percent of time the virtual machine is unable to run because it is contending for access to the physical CPU(s), "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'vmware.vm.cpu.num[url,uuid]' => [
				'description' => t('zapi', 'Number of processors on VMware virtual machine, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.cpu.readiness[url,uuid,<instance>]' => [
				'description' => t('zapi', 'Percentage of time that the virtual machine was ready, but could not get scheduled to run on the physical CPU, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier, "instance" - CPU instance'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'vmware.vm.cpu.ready[url,uuid]' => [
				'description' => t('zapi', 'VMware virtual machine processor ready time ms, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.cpu.swapwait[url,uuid,<instance>]' => [
				'description' => t('zapi', 'CPU time spent waiting for swap-in, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier, "instance" - CPU instance'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.cpu.usage.perf[url,uuid]' => [
				'description' => t('zapi', 'CPU usage as a percentage during the interval, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.cpu.usage[url,uuid]' => [
				'description' => t('zapi', 'VMware virtual machine processor usage in Hz, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.datacenter.name[url,uuid]' => [
				'description' => t('zapi', 'VMware virtual machine datacenter name, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.vm.discovery[url]' => [
				'description' => t('zapi', 'Discovery of VMware virtual machines, "url" - VMware service URL. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.vm.guest.memory.size.swapped[url,uuid]' => [
				'description' => t('zapi', 'Amount of guest physical memory that is swapped out to the swap space, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.guest.osuptime[url,uuid]' => [
				'description' => t('zapi', 'Total time elapsed, in seconds, since last operating system boot-up, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.hv.name[url,uuid]' => [
				'description' => t('zapi', 'VMware virtual machine hypervisor name, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.vm.memory.size.ballooned[url,uuid]' => [
				'description' => t('zapi', 'VMware virtual machine ballooned memory size, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.memory.size.compressed[url,uuid]' => [
				'description' => t('zapi', 'VMware virtual machine compressed memory size, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.memory.size.consumed[url,uuid]' => [
				'description' => t('zapi', 'Amount of host physical memory consumed for backing up guest physical memory pages, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.memory.size.private[url,uuid]' => [
				'description' => t('zapi', 'VMware virtual machine private memory size, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifierr'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.memory.size.shared[url,uuid]' => [
				'description' => t('zapi', 'VMware virtual machine shared memory size, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.memory.size.swapped[url,uuid]' => [
				'description' => t('zapi', 'VMware virtual machine swapped memory size, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.memory.size.usage.guest[url,uuid]' => [
				'description' => t('zapi', 'VMware virtual machine guest memory usage, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.memory.size.usage.host[url,uuid]' => [
				'description' => t('zapi', 'VMware virtual machine host memory usage, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.memory.size[url,uuid]' => [
				'description' => t('zapi', 'VMware virtual machine total memory size, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.memory.usage[url,uuid]' => [
				'description' => t('zapi', 'Percentage of host physical memory that has been consumed, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'vmware.vm.net.if.discovery[url,uuid]' => [
				'description' => t('zapi', 'Discovery of VMware virtual machine network interfaces, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.vm.net.if.in[url,uuid,instance,<mode>]' => [
				'description' => t('zapi', 'VMware virtual machine network interface input statistics, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier, "instance" - network interface instance, "mode"- bps/pps - bytes/packets per second'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'vmware.vm.net.if.out[url,uuid,instance,<mode>]' => [
				'description' => t('zapi', 'VMware virtual machine network interface output statistics, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier, "instance" - network interface instance, "mode"- bps/pps - bytes/packets per second'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'vmware.vm.net.if.usage[url,uuid,<instance>]' => [
				'description' => t('zapi', 'Network utilization (combined transmit-rates and receive-rates) during the interval, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier, "instance" - network interface instance'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'vmware.vm.perfcounter[url,uuid,path,<instance>]' => [
				'description' => t('zapi', 'VMware virtual machine performance counter, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier, "path" - performance counter path, "instance" - performance counter instance'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'vmware.vm.powerstate[url,uuid]' => [
				'description' => t('zapi', 'VMware virtual machine power state, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.property[url,uuid,prop]' => [
				'description' => t('zapi', 'VMware virtual machine property, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier, "prop" - property path'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.vm.snapshot.get[url,uuid]' => [
				'description' => t('zapi', 'VMware virtual machine snapshot state, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.vm.state[url,uuid]' => [
				'description' => t('zapi', 'VMware virtual machine state, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.vm.storage.committed[url,uuid]' => [
				'description' => t('zapi', 'VMware virtual machine committed storage space, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => null
			],
			'vmware.vm.storage.readoio[url,uuid,instance]' => [
				'description' => t('zapi', 'Average number of outstanding read requests to the virtual disk during the collection interval , "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier, "instance" - disk device instance'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.storage.totalreadlatency[url,uuid,instance]' => [
				'description' => t('zapi', 'The average time a read from the virtual disk takes, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier, "instance" - disk device instance'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.storage.totalwritelatency[url,uuid,instance]' => [
				'description' => t('zapi', 'The average time a write to the virtual disk takes, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier, "instance" - disk device instance'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.storage.uncommitted[url,uuid]' => [
				'description' => t('zapi', 'VMware virtual machine uncommitted storage space, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.storage.unshared[url,uuid]' => [
				'description' => t('zapi', 'VMware virtual machine unshared storage space, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.storage.writeoio[url,uuid,instance]' => [
				'description' => t('zapi', 'Average number of outstanding write requests to the virtual disk during the collection interval, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier, "instance" - disk device instance'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.tags.get[url,uuid]' => [
				'description' => t('zapi', 'VMware virtual machine tags array, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.vm.tools[url,uuid,mode]' => [
				'description' => t('zapi', 'VMware virtual machine tools state, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier, "mode"- version or status'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.vm.uptime[url,uuid]' => [
				'description' => t('zapi', 'VMware virtual machine uptime, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.vfs.dev.discovery[url,uuid]' => [
				'description' => t('zapi', 'Discovery of VMware virtual machine disk devices, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.vm.vfs.dev.read[url,uuid,instance,<mode>]' => [
				'description' => t('zapi', 'VMware virtual machine disk device read statistics, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier, "instance" - disk device instance, "mode"- bps/ops - bytes/operations per second'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'vmware.vm.vfs.dev.write[url,uuid,instance,<mode>]' => [
				'description' => t('zapi', 'VMware virtual machine disk device write statistics, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier, "instance" - disk device instance, "mode"- bps/ops - bytes/operations per second'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'vmware.vm.vfs.fs.discovery[url,uuid]' => [
				'description' => t('zapi', 'Discovery of VMware virtual machine file systems, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.vm.vfs.fs.size[url,uuid,fsname,<mode>]' => [
				'description' => t('zapi', 'VMware virtual machine file system statistics, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier, "fsname" - file system name, "mode"- total/free/used/pfree/pused'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'web.page.get[host,<path>,<port>]' => [
				'description' => t('zapi', 'Get content of web page. Returns web page source as text'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'web.page.perf[host,<path>,<port>]' => [
				'description' => t('zapi', 'Loading time of full web page (in seconds). Returns float'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'web.page.regexp[host,<path>,<port>,regexp,<length>,<output>]' => [
				'description' => t('zapi', 'Find string on a web page. Returns the matched string, or as specified by the optional output parameter'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'wmi.get[namespace,query]' => [
				'description' => t('zapi', 'Execute WMI query and return the first selected object. Returns integer, float, string or text (depending on the request)'),
				'value_type' => null
			],
			'wmi.getall[namespace,query]' => [
				'description' => t('zapi', 'Execute WMI query and return the JSON document with all selected objects'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'perseus.stats[<ip>,<port>,queue,<from>,<to>]' => [
				'description' => t('zapi', 'Number of items in the queue which are delayed in Perseus server or proxy by "from" till "to" seconds, inclusive.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'perseus.stats[<ip>,<port>]' => [
				'description' => t('zapi', 'Returns a JSON object containing Perseus server or proxy internal metrics.'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'perseus[boottime]' => [
				'description' => t('zapi', 'Startup time of Perseus server, Unix timestamp.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'perseus[connector_queue]' => [
				'description' => t('zapi', 'Count of values enqueued in the connector queue.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'perseus[host,,items]' => [
				'description' => t('zapi', 'Number of enabled items on the host.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'perseus[host,,items_unsupported]' => [
				'description' => t('zapi', 'Number of unsupported items on the host.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'perseus[host,,maintenance]' => [
				'description' => t('zapi', 'Returns current maintenance status of the host.'),
				'value_type' => null
			],
			'perseus[host,<type>,available]' => [
				'description' => t('zapi', 'Returns availability of a particular type of checks on the host. Value of this item corresponds to availability icons in the host list. Valid types are: agent, active_agent, snmp, ipmi, jmx.'),
				'value_type' => null
			],
			'perseus[host,discovery,interfaces]' => [
				'description' => t('zapi', 'Returns a JSON array describing the host network interfaces configured in Perseus. Can be used for LLD.'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'perseus[hosts]' => [
				'description' => t('zapi', 'Number of monitored hosts'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'perseus[items]' => [
				'description' => t('zapi', 'Number of items in Perseus database.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'perseus[items_unsupported]' => [
				'description' => t('zapi', 'Number of unsupported items in Perseus database.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'perseus[java,,<param>]' => [
				'description' => t('zapi', 'Returns information associated with Perseus Java gateway. Valid params are: ping, version.'),
				'value_type' => null
			],
			'perseus[lld_queue]' => [
				'description' => t('zapi', 'Count of values enqueued in the low-level discovery processing queue.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'perseus[preprocessing_queue]' => [
				'description' => t('zapi', 'Count of values enqueued in the preprocessing queue.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'perseus[process,<type>,<mode>,<state>]' => [
				'description' => t('zapi', 'Time a particular Perseus process or a group of processes (identified by <type> and <mode>) spent in <state> in percentage.'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'perseus[proxy,<name>,<param>]' => [
				'description' => t('zapi', 'Time of proxy last access. Name - proxy name. Valid params are: lastaccess - Unix timestamp, delay - seconds.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'perseus[proxy,discovery]' => [
				'description' => t('zapi', 'List of Perseus proxies with name, mode, encryption, compression, version, last seen, host count, item count, required values per second (vps) and compatibility (current/outdated/unsupported). Returns JSON.'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'perseus[proxy_history]' => [
				'description' => t('zapi', 'Number of items in proxy history that are not yet sent to the server'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'perseus[queue,<from>,<to>]' => [
				'description' => t('zapi', 'Number of items in the queue which are delayed by from to seconds, inclusive.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'perseus[rcache,<cache>,<mode>]' => [
				'description' => t('zapi', 'Configuration cache statistics. Cache - buffer (modes: pfree, total, used, free).'),
				'value_type' => null
			],
			'perseus[requiredperformance]' => [
				'description' => t('zapi', 'Required performance of the Perseus server, in new values per second expected.'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'perseus[stats,<ip>,<port>,queue,<from>,<to>]' => [
				'description' => t('zapi', 'Number of items in the queue which are delayed in Perseus server or proxy by "from" till "to" seconds, inclusive.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'perseus[stats,<ip>,<port>]' => [
				'description' => t('zapi', 'Returns a JSON object containing Perseus server or proxy internal metrics.'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'perseus[tcache, cache, <parameter>]' => [
				'description' => t('zapi', 'Trend function cache statistics. Valid parameters are: all, hits, phits, misses, pmisses, items, pitems and requests.'),
				'value_type' => null
			],
			'perseus[triggers]' => [
				'description' => t('zapi', 'Number of triggers in Perseus database.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'perseus[uptime]' => [
				'description' => t('zapi', 'Uptime of Perseus server process in seconds.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'perseus[vcache,buffer,<mode>]' => [
				'description' => t('zapi', 'Value cache statistics. Valid modes are: total, free, pfree, used and pused.'),
				'value_type' => null
			],
			'perseus[vcache,cache,<parameter>]' => [
				'description' => t('zapi', 'Value cache effectiveness. Valid parameters are: requests, hits and misses.'),
				'value_type' => null
			],
			'perseus[version]' => [
				'description' => t('zapi', 'Version of Perseus server or proxy'),
				'value_type' => null
			],
			'perseus[vmware,buffer,<mode>]' => [
				'description' => t('zapi', 'VMware cache statistics. Valid modes are: total, free, pfree, used and pused.'),
				'value_type' => null
			],
			'perseus[wcache,<cache>,<mode>]' => [
				'description' => t('zapi', 'Statistics and availability of Perseus write cache. Cache - one of values (modes: all, float, uint, str, log, text, not supported), history (modes: pfree, free, total, used, pused), index (modes: pfree, free, total, used, pused), trend (modes: pfree, free, total, used, pused).'),
				'value_type' => null
			]
		];
	}
}
